<?php

namespace App\Services;

use App\Enums\Payer;
use App\Enums\RepairStatus;
use App\Enums\Role;
use App\Exceptions\WorkflowException;
use App\Models\Attachment;
use App\Models\Building;
use App\Models\RepairCategory;
use App\Models\RepairEvent;
use App\Models\RepairRequest;
use App\Models\User;
use App\Notifications\RepairAssigned;
use App\Notifications\RepairCompleted;
use App\Notifications\RepairReported;
use App\Notifications\RepairScheduled;
use App\Notifications\RepairStatusChanged;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Obravnava prijave okvare (F7, ADR-0006, ADR-0007).
 *
 * Vsak korak je ločena metoda, ki preveri dovoljen prehod stanja in zapiše dogodek
 * z avtorjem in časom, kar je podlaga za časovnico.
 */
class RepairWorkflow
{
    /**
     * Najemnik (ali lastnik) prijavi okvaro (US-09). Privzeti plačnik izhaja iz vrste okvare.
     *
     * @param  array{title: string, description: string, category_id: int, apartment_id?: ?int, shared_space_id?: ?int}  $data
     * @param  list<UploadedFile>  $photos
     */
    public function report(Building $building, User $reporter, array $data, array $photos = []): RepairRequest
    {
        $category = RepairCategory::findOrFail($data['category_id']);

        $request = DB::transaction(function () use ($building, $reporter, $data, $photos, $category) {
            $request = RepairRequest::create([
                'building_id' => $building->id,
                'apartment_id' => $data['apartment_id'] ?? null,
                'shared_space_id' => $data['shared_space_id'] ?? null,
                'reported_by' => $reporter->id,
                'category_id' => $category->id,
                'title' => $data['title'],
                'description' => $data['description'],
                'status' => RepairStatus::Reported,
                'default_payer' => $category->default_payer,
                'payer' => $category->default_payer,
            ]);

            $this->storePhotos($request, $reporter, $photos, 'report');

            $this->event($request, $reporter, null, RepairStatus::Reported, count($photos) > 0
                ? 'priložene '.count($photos).' '.(count($photos) === 1 ? 'fotografija' : 'fotografije')
                : null);
            $this->event($request, null, RepairStatus::Reported, RepairStatus::Reported,
                'iz vrste okvare »'.$category->name.'«', 'payer', ['payer' => $category->default_payer->value]);

            return $request;
        });

        // Lastnik prejme e-pošto s povezavo na prijavo.
        Notification::send($this->landlords($building), new RepairReported($request));

        return $request;
    }

    /**
     * Lastnik potrdi prijavo (US-10, #121).
     */
    public function confirm(RepairRequest $repair, User $landlord): RepairRequest
    {
        return $this->step($repair, $landlord, [RepairStatus::Reported], RepairStatus::Confirmed);
    }

    /**
     * Lastnik zavrne prijavo z obvezno obrazložitvijo, ki jo najemnik vidi v časovnici (US-10, #121).
     */
    public function reject(RepairRequest $repair, User $landlord, string $reason): RepairRequest
    {
        return $this->step($repair, $landlord, [RepairStatus::Reported], RepairStatus::Rejected, [], $reason);
    }

    /**
     * Sprememba plačnika s prejšnjo in novo vrednostjo, avtorjem in časom (US-10, #122).
     */
    public function changePayer(RepairRequest $repair, User $landlord, Payer $payer): RepairRequest
    {
        if (in_array($repair->status, [RepairStatus::Closed, RepairStatus::Rejected], true)) {
            throw new WorkflowException('Plačnika zaključene ali zavrnjene prijave ni mogoče spremeniti.');
        }

        if ($repair->payer === $payer) {
            return $repair;
        }

        DB::transaction(function () use ($repair, $landlord, $payer) {
            $from = $repair->payer;
            $repair->update(['payer' => $payer]);
            $this->event($repair, $landlord, $repair->status, $repair->status,
                $from->label().' → '.$payer->label(), 'payer', ['from' => $from->value, 'payer' => $payer->value]);
        });

        return $repair->refresh();
    }

    /**
     * Dodelitev potrjene prijave izvajalcu, povabljenemu v stavbo (US-10, #123).
     */
    public function assign(RepairRequest $repair, User $landlord, User $contractor): RepairRequest
    {
        if (! $contractor->hasRole($repair->building_id, Role::Contractor)) {
            throw ValidationException::withMessages(['contractor_id' => 'Izberete lahko samo izvajalca, povabljenega v stavbo.']);
        }

        $repair = $this->step($repair, $landlord, [RepairStatus::Confirmed], RepairStatus::Assigned,
            ['contractor_id' => $contractor->id], $contractor->name);

        $contractor->notify(new RepairAssigned($repair));

        return $repair;
    }

    /**
     * Izvajalec sporoči termin izvedbe v prihodnosti (US-11, #125).
     * Najemnik in lastnik prejmeta e-pošto s terminom.
     */
    public function schedule(RepairRequest $repair, User $contractor, CarbonInterface $scheduledAt): RepairRequest
    {
        if ($scheduledAt->lte(now())) {
            throw ValidationException::withMessages(['scheduled_at' => 'Termin izvedbe mora biti v prihodnosti.']);
        }

        $repair = $this->step($repair, $contractor, [RepairStatus::Assigned], RepairStatus::Scheduled,
            ['scheduled_at' => $scheduledAt], 'termin '.$scheduledAt->format('j. n. Y \o\b H:i'));

        Notification::send($this->landlords($repair->building_id), new RepairScheduled($repair));

        return $repair;
    }

    /**
     * Izvajalec odda poročilo s stroškom in vsaj eno fotografijo (US-11, #126).
     * Ob ponovni oddaji po vrnitvi v popravek se prejšnje fotografije izvedbe zamenjajo.
     *
     * @param  list<UploadedFile>  $photos
     */
    public function complete(RepairRequest $repair, User $contractor, int $costCents, array $photos): RepairRequest
    {
        if ($costCents < 0) {
            throw ValidationException::withMessages(['cost_cents' => 'Strošek ne sme biti negativen.']);
        }
        if (count($photos) === 0) {
            throw ValidationException::withMessages(['photos' => 'Priložite vsaj eno fotografijo opravljenega dela.']);
        }
        if ($repair->status !== RepairStatus::Scheduled) {
            throw new WorkflowException('Kot opravljen je mogoče označiti samo nalog z dogovorjenim terminom.');
        }

        $previous = $repair->attachments()->where('context', 'completion')->get();

        $repair = DB::transaction(function () use ($repair, $contractor, $costCents, $photos) {
            $this->storePhotos($repair, $contractor, $photos, 'completion');

            return $this->step($repair, $contractor, [RepairStatus::Scheduled], RepairStatus::Done,
                ['cost_cents' => $costCents], 'strošek '.Money::format($costCents).', '.count($photos).' fotografij');
        });

        $previous->each(fn (Attachment $a) => $this->deletePhoto($a));

        Notification::send($this->landlords($repair->building_id), new RepairCompleted($repair));

        return $repair;
    }

    /**
     * Prehod stanja v transakciji z obvestilom prijavitelju o spremembi (US-10, #123).
     *
     * @param  list<RepairStatus>  $allowedFrom
     */
    protected function step(RepairRequest $repair, User $user, array $allowedFrom, RepairStatus $to, array $attributes = [], ?string $note = null): RepairRequest
    {
        $event = DB::transaction(function () use ($repair, $user, $allowedFrom, $to, $attributes, $note) {
            $locked = RepairRequest::whereKey($repair->id)->lockForUpdate()->firstOrFail();
            $repair->setRawAttributes($locked->getAttributes(), true);

            return $this->transition($repair, $user, $allowedFrom, $to, $attributes, $note);
        });

        // Prijavitelj prejme e-pošto ob vsaki spremembi stanja, ki je ni opravil sam.
        if ($repair->reported_by !== $user->id) {
            $repair->reporter->notify(new RepairStatusChanged($repair, $event));
        }

        return $repair->refresh();
    }

    /**
     * @param  list<UploadedFile>  $photos
     */
    public function storePhotos(RepairRequest $request, User $user, array $photos, string $context): void
    {
        foreach ($photos as $photo) {
            $path = $photo->store("repairs/{$request->id}", 'local');

            Attachment::create([
                'attachable_type' => $request->getMorphClass(),
                'attachable_id' => $request->id,
                'uploaded_by' => $user->id,
                'context' => $context,
                'path' => $path,
                'original_name' => $photo->getClientOriginalName(),
                'mime' => $photo->getMimeType(),
                'size' => $photo->getSize(),
            ]);
        }
    }

    /**
     * Zapiše korak v časovnico.
     */
    protected function event(
        RepairRequest $request,
        ?User $user,
        ?RepairStatus $from,
        RepairStatus $to,
        ?string $note = null,
        string $kind = 'status',
        ?array $meta = null,
    ): RepairEvent {
        return RepairEvent::create([
            'repair_request_id' => $request->id,
            'user_id' => $user?->id,
            'kind' => $kind,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'note' => $note,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }

    /**
     * Prehod stanja s preverjanjem trenutnega stanja (neveljaven korak vrne 409).
     *
     * @param  list<RepairStatus>  $allowedFrom
     */
    protected function transition(RepairRequest $request, User $user, array $allowedFrom, RepairStatus $to, array $attributes = [], ?string $note = null): RepairEvent
    {
        if (! in_array($request->status, $allowedFrom, true)) {
            throw new WorkflowException(sprintf(
                'Prehod iz stanja »%s« v »%s« ni dovoljen.',
                $request->status->label(),
                $to->label()
            ));
        }

        $from = $request->status;
        $request->update([...$attributes, 'status' => $to]);

        return $this->event($request, $user, $from, $to, $note);
    }

    protected function landlords(Building|int $building)
    {
        $buildingId = $building instanceof Building ? $building->id : $building;

        return User::whereHas('memberships', fn ($q) => $q->where('building_id', $buildingId)->where('role', Role::Landlord))->get();
    }

    public function deletePhoto(Attachment $attachment): void
    {
        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();
    }
}
