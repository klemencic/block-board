# Poslovna logika v storitvenih razredih

## Kontekst in opis problema

BlockBoard ima več zahtevnih domenskih pravil: delitev položnice med stanovanja in sostanovalce z razporeditvijo ostanka centov, preverjanje obratovalnega časa in tedenske omejitve rezervacij, primerjavo zapisnikov, obračun varščine in dovoljene prehode med stanji. Ta pravila morajo biti zanesljivo testirana, nekatera pa se ne kličejo samo iz API-ja, ampak tudi iz delavca v ozadju (mesečne obveznosti). Odločiti se je treba, kje v zalednem delu je ta logika.

## Obravnavane možnosti

* Storitveni razredi (`BillSplitter`, `BookingService`, `DepositSettlement` …)
* Logika v krmilnikih
* Logika v modelih Eloquent

## Odločitev

Izbrana možnost: »Storitveni razredi«, ker je pravila tako mogoče testirati ločeno od HTTP-ja in baze, ista koda pa se uporablja v API-ju in v delavcu v ozadju. Krmilniki ostanejo tanki: preverijo vhod in pravice, pokličejo storitev in vrnejo odgovor.

### Posledice

* Dobro, ker so testi za delitev položnic, rezervacije in varščino hitri in neodvisni od zahtev HTTP.
* Dobro, ker `ChargeGenerator` uporabljata tako razporejevalnik kot API, brez podvojene kode.
* Dobro, ker je vsako pravilo na enem znanem mestu, kar olajša spremembe.
* Slabo, ker je razredov več in je treba za vsako pravilo odločiti, kateri storitvi pripada.
* Slabo, ker modeli lahko postanejo zgolj nosilci podatkov, logika pa se razprši po storitvah, če meje niso jasne.
