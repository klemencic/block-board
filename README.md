# BlockBoard

BlockBoard je spletna aplikacija za vodenje najemniške stavbe, v kateri ima vsa stanovanja en lastnik. Na enem mestu vodi celotno najemno razmerje, od vselitve do vračila varščine:

- najeme z življenjskim ciklom in več sostanovalci,
- mesečne obveznosti in delitev položnic po dnevih bivanja,
- rezervacije skupnih prostorov brez prekrivanja,
- primopredajne zapisnike s primerjavo stanja ob vselitvi in izselitvi,
- obračun varščine z odbitki, vezanimi na razlike v zapisniku,
- prijave okvar s pravilom plačnika in dodelitvijo izvajalcu.

Aplikacijo uporabljajo tri vloge: **lastnik stavbe**, **najemnik** in **izvajalec del**.

## Uporabljene tehnologije

| Področje | Tehnologije |
| --- | --- |
| Frontend | Vue 3.5, TypeScript, Vite 8, Vue Router 5, Pinia, Tailwind CSS, Axios |
| Backend | PHP 8.4, Laravel 13, Eloquent ORM, Laravel Queue, Laravel Scheduler |
| Podatkovna baza | PostgreSQL 18 |
| Avtentikacija | Laravel Sanctum (sejni piškotki), Laravel Policies |
| Lokalni razvoj | Docker, Docker Compose, Mailpit, Git |
| Testiranje | Pest, Vitest |

## Dokumentacija

Dokumentacija je v direktoriju [docs/](docs/):

1. [Zasnova rešitve](docs/01-zasnova-resitve.md)
2. [Funkcionalnosti in obseg](docs/02-funkcionalnosti-in-obseg.md)
3. [Podatkovni model](docs/03-podatkovni-model.md)
4. [Arhitektura in tehnološki sklad](docs/04-arhitektura-in-tehnoloski-sklad.md)
5. [Uporabniške zgodbe](docs/05-uporabniske-zgodbe.md)
6. [Wireframes](docs/06-wireframes.md)

### Arhitekturne odločitve (ADR)

Arhitekturne odločitve so zapisane v [docs/adr/](docs/adr/) po minimalni predlogi [MADR](https://github.com/adr/madr/blob/develop/template/adr-template-minimal.md) ([adr-template.md](docs/adr/adr-template.md)).

| ADR | Odločitev |
| --- | --- |
| [0001](docs/adr/0001-locena-spa-in-rest-api.md) | Ločena Vue SPA in REST API |
| [0002](docs/adr/0002-laravel-kot-zaledno-ogrodje.md) | Laravel kot zaledno ogrodje |
| [0003](docs/adr/0003-postgresql-kot-podatkovna-baza.md) | PostgreSQL kot podatkovna baza |
| [0004](docs/adr/0004-avtentikacija-s-sejnim-piskotkom.md) | Avtentikacija s sejnim piškotkom |
| [0005](docs/adr/0005-vloga-vezana-na-clanstvo-v-stavbi.md) | Vloga vezana na članstvo v stavbi |
| [0006](docs/adr/0006-namenske-koncne-tocke-za-prehode-stanj.md) | Namenske končne točke za prehode stanj |
| [0007](docs/adr/0007-poslovna-logika-v-storitvenih-razredih.md) | Poslovna logika v storitvenih razredih |
| [0008](docs/adr/0008-lokalno-okolje-z-docker-compose.md) | Lokalno okolje z Docker Compose |

Nove pomembne odločitve se dodajo kot nov ADR z naslednjo številko. Če se sprejeta odločitev spremeni, nastane nov ADR, ki v kontekstu navede, katerega nadomešča (na primer »Nadomešča [ADR-0003](docs/adr/0003-postgresql-kot-podatkovna-baza.md)«), v starem ADR pa se pod naslov doda opomba »Nadomeščeno z ADR-XXXX«.

## Razvojni proces

Veja `main` je zaščitena. Spremembe se razvijajo na ločenih vejah in se v `main` združijo prek pull requesta. Prisilno prepisovanje zgodovine in brisanje veje `main` nista dovoljena.

## Struktura repozitorija

```text
block-board/
  frontend/    Vue SPA (v pripravi)
  backend/     Laravel API (v pripravi)
  docs/        dokumentacija, wireframes in ADR
```
