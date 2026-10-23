---
status: sprejeto
date: 2026-10-23
---

# Avtentikacija SPA s sejnim piškotkom (Laravel Sanctum)

## Kontekst in opis problema

SPA mora uporabnika prijaviti in vsako zahtevo na API poslati v njegovem imenu. Aplikacija hrani osebne podatke najemnikov, najemne pogoje in finančne obveznosti, zato je pomembno, kje so podatki o prijavi shranjeni, kdo jih lahko prebere in ali odjava sejo dejansko konča. SPA in API sta v razvoju dostopna prek istega izvora (posredovanje v Vite).

## Obravnavane možnosti

* Sejni piškotek (Laravel Sanctum, način SPA)
* Žeton JWT v `localStorage`
* OAuth 2 strežnik (Laravel Passport)

## Odločitev

Izbrana možnost: »Sejni piškotek (Laravel Sanctum)«, ker je piškotek označen kot `HttpOnly` in ga skripte v brskalniku ne morejo prebrati, zato ga ni mogoče ukrasti z vstavljeno skripto. Seja je shranjena na strežniku, zato jo odjava dejansko konča. Ker SPA in API delujeta na istem izvoru, ni potrebna nastavitev CORS in frontendu ni treba upravljati z žetoni.

### Posledice

* Dobro, ker v brskalniku ni shranjen noben žeton.
* Dobro, ker je sejo mogoče kadarkoli preklicati na strežniku (odjava, odstranitev uporabnika iz stavbe).
* Dobro, ker je obravnava prijave v SPA preprosta: Axios pošilja piškotek in žeton CSRF samodejno.
* Slabo, ker morata biti SPA in API na istem izvoru ali istem nadrejenem domenskem imenu, kar omejuje razmestitev.
* Slabo, ker je zaradi piškotkov potrebna zaščita CSRF pri vsaki zahtevi, ki spreminja podatke.
* Slabo, ker bo mobilna aplikacija potrebovala drug mehanizem (žetone API Sanctum), torej bosta kasneje v uporabi dva načina prijave.

## Prednosti in slabosti možnosti

### Sejni piškotek (Sanctum)

* Dobro, ker je varen privzeto in vgrajen v Laravel.
* Slabo, ker ni primeren za odjemalce zunaj brskalnika.

### Žeton JWT v `localStorage`

* Dobro, ker strežnik ne hrani stanja seje in deluje tudi med različnimi domenami.
* Slabo, ker lahko žeton prebere vsaka skripta na strani, odjava pa ga ne razveljavi, dokler ne poteče.

### OAuth 2 strežnik (Passport)

* Dobro, ker podpira zunanje odjemalce in različne načine pooblaščanja.
* Slabo, ker je za lastno SPA brez zunanjih odjemalcev nepotrebno zapleten.

## Dodatne informacije

* Avtentikacija in avtorizacija: [04-arhitektura-in-tehnoloski-sklad.md](../04-arhitektura-in-tehnoloski-sklad.md)
* Povezano: [ADR-0001](0001-locena-spa-in-rest-api.md), [ADR-0006](0006-vloga-vezana-na-clanstvo-v-stavbi.md)
