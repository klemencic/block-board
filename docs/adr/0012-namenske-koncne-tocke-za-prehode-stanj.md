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
