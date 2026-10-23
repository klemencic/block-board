# Laravel kot zaledno ogrodje

## Kontekst in opis problema

Zaledni del mora poleg REST API-ja zagotoviti validacijo vhodnih podatkov, preverjanje pravic po vlogah, migracije sheme, opravila v ozadju (pošiljanje e-pošte), časovno določena opravila (mesečne obveznosti, opozorila pred iztekom najema, zaključevanje preteklih rezervacij) in shranjevanje fotografij. Projekt razvija ena oseba v omejenem času, zato je pomembno, koliko od tega je treba sestaviti in povezati ročno.

## Obravnavane možnosti

* Laravel (PHP)
* NestJS (TypeScript)
* Spring Boot (Java)

## Odločitev

Izbrana možnost: »Laravel«, ker so validacija, razredi Policy, vrsta opravil, razporejevalnik, pošiljanje e-pošte, datotečna shramba, migracije in avtentikacija za SPA (Sanctum) že del ogrodja in delujejo skupaj po istih konvencijah. Čas gre tako v domensko logiko (delitev položnic, varščina, rezervacije) in ne v povezovanje ločenih paketov. Ogrodje je stabilno in dobro dokumentirano.

### Posledice

* Dobro, ker je za vse potrebne zmožnosti en sam nabor konvencij in dokumentacije.
* Dobro, ker delavec vrste in razporejevalnik tečeta iz iste kode kot API, zato poslovna pravila niso podvojena.
* Dobro, ker ima Laravel dobro podporo za testiranje (Pest, testna baza, lažna e-pošta in vrsta opravil).
* Slabo, ker je backend v PHP, frontend pa v TypeScriptu, zato tipov podatkov ni mogoče deliti med deloma in jih je treba usklajevati ročno.
* Slabo, ker Eloquent skrije poizvedbe, zato je treba paziti na težavo N+1 in poizvedbe preverjati.
* Slabo, ker smo vezani na cikel izdaj ogrodja in bo treba nadgradnje glavnih različic opraviti sproti.
