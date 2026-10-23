---
status: sprejeto
date: 2026-10-23
---

# Spremembe stanja prek namenskih končnih točk s preverjanjem prehoda

## Kontekst in opis problema

Več entitet ima življenjski cikel: najem (osnutek, aktiven, v odpovednem roku, zaključen), zapisnik (osnutek, potrjen), odbitek (predlagan, potrjen, sporen, umaknjen) in prijava okvare (prijavljeno, potrjeno, dodeljeno, termin, opravljeno, zaključeno). Prehodi imajo pravila: potrjenega zapisnika ni mogoče spreminjati, najema s spornimi odbitki ni mogoče zaključiti, zapisnik lahko potrdi samo najemnik. Pri nekaterih prehodih se mora zgoditi še kaj drugega, na primer izračun datuma prenehanja ob odpovedi. Odločiti se je treba, kako API omogoča spremembe stanja.

## Obravnavane možnosti

* Namenske končne točke za vsak korak (na primer `POST /api/leases/{id}/give-notice`)
* Splošno urejanje polja `status` (`PATCH /api/leases/{id}`)
* Ena splošna končna točka za prehode (`POST /api/leases/{id}/transitions` s ciljnim stanjem)

## Odločitev

Izbrana možnost: »Namenske končne točke«, ker ima vsak korak svoje vhodne podatke, svoje pravice in svoje stranske učinke. Ločena končna točka vse to zbere na enem mestu, zato neveljaven korak ni mogoč. Vsak prehod se zapiše s prejšnjim in novim stanjem, avtorjem in časom, kar je podlaga za časovnico prijave okvare.

### Posledice

* Dobro, ker vsaka končna točka preveri samo svoj prehod in vrne `409`, če ta v trenutnem stanju ni dovoljen.
* Dobro, ker je pravica za korak jasno določena v svojem razredu Policy (na primer potrditev zapisnika samo za najemnika).
* Dobro, ker so stranski učinki (izračun datuma prenehanja, obveznost za popravilo, e-pošta) vezani na korak in ne na spremembo polja.
* Dobro, ker je API samopojasnjujoč: seznam končnih točk je hkrati seznam dovoljenih dejanj.
* Slabo, ker je končnih točk, poti in testov več kot pri splošnem urejanju.
* Slabo, ker vsak nov korak v procesu zahteva novo končno točko, ne le nove vrednosti stanja.

## Prednosti in slabosti možnosti

### Namenske končne točke

* Dobro, ker je vsak korak ločeno preverjen in testiran.
* Slabo, ker je API obsežnejši.

### Splošno urejanje polja `status`

* Dobro, ker je najmanj kode in sledi preprostemu vzorcu CRUD.
* Slabo, ker je treba vsa pravila prehodov in stranske učinke izvesti v eni metodi za posodabljanje, kjer jih je lahko izpustiti.

### Ena splošna končna točka za prehode

* Dobro, ker je prehode mogoče opisati s tabelo stanj.
* Slabo, ker imajo koraki različne vhodne podatke (obrazložitev zavrnitve, izvajalec in plačnik, datum odpovedi), zato validacija postane razvejana.

## Dodatne informacije

* Komunikacija in primeri končnih točk: [04-arhitektura-in-tehnoloski-sklad.md](../04-arhitektura-in-tehnoloski-sklad.md)
* Povezano: [ADR-0001](0001-locena-spa-in-rest-api.md), [ADR-0015](0015-poslovna-logika-v-storitvenih-razredih.md)
