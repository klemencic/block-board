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

## Lokalni zagon

Za zagon potrebujete samo [Docker Desktop](https://www.docker.com/products/docker-desktop/) (ali Docker Engine z Docker Compose). PHP, Composer, Node in PostgreSQL se zaženejo v vsebnikih.

```bash
git clone https://github.com/klemencic/block-board.git
cd block-board

cp backend/.env.example backend/.env
docker compose build
docker compose run --rm api composer install
docker compose up -d
docker compose exec api php artisan key:generate
docker compose exec api php artisan migrate --seed
```

Prvi zagon traja nekaj minut, ker se prenesejo slike in odvisnosti. Storitev `web` ob zagonu sama izvede `npm install`, zato je aplikacija na voljo približno minuto po ukazu `docker compose up -d` (napredek: `docker compose logs -f web`).

| Naslov | Vsebina |
| --- | --- |
| http://localhost:5173 | Aplikacija (Vue SPA) |
| http://localhost:8000 | Laravel API |
| http://localhost:8025 | Mailpit – vsa poslana e-pošta |

### Demo računi

Vsi računi imajo geslo `geslo123`.

| Vloga | E-naslov |
| --- | --- |
| Lastnik stavbe | `lastnik@blockboard.test` |
| Najemnik (stanovanje 2C) | `jan@blockboard.test` |
| Izvajalec del | `breznik@blockboard.test` |

### Pogosti ukazi

```bash
docker compose exec api php artisan migrate:fresh --seed   # ponastavi bazo in demo podatke
docker compose exec api vendor/bin/pest                    # testi backenda (Pest)
docker compose exec web npm test                           # testi frontenda (Vitest)
docker compose logs -f worker                              # vrsta opravil in razporejevalnik
docker compose down                                        # ustavi vse storitve
```

Testi backenda uporabljajo ločeno bazo `blockboard_test`, ki se ustvari ob prvem zagonu storitve `db`.

## Razvojni proces

Veja `main` je zaščitena. Spremembe se razvijajo na ločenih vejah in se v `main` združijo prek pull requesta. Prisilno prepisovanje zgodovine in brisanje veje `main` nista dovoljena.

## Struktura repozitorija

```text
block-board/
  frontend/            Vue SPA (Vite, TypeScript, Tailwind)
  backend/             Laravel API (Sanctum, Pest)
  docker/              Dockerfile za PHP in začetni skript baze
  docs/                dokumentacija, wireframes in ADR
  docker-compose.yml   storitve api, worker, db, web in mail
```
