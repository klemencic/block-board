---
status: sprejeto
date: 2026-10-23
---

# Ena tabela obveznosti za vse vrste zneskov

## Kontekst in opis problema

Najemnik lastniku dolguje več vrst zneskov: mesečno najemnino, deleže položnic, cene rezervacij skupnih prostorov in popravila v svoje breme. Vsak izvira iz drugega dela sistema. Najemnik želi en mesečni obračun in eno stanje dolga, lastnik pa na enem mestu označuje plačila in vidi zamude. Odločiti se je treba, ali so te obveznosti zapisane skupaj ali ločeno po izvoru.

## Obravnavane možnosti

* Ena tabela `Charge` z vrsto in povezavo na izvor
* Ločene tabele za najemnino, stroške, rezervacije in popravila
* Ločene tabele, združene s pogledom (`UNION`) za prikaz

## Odločitev

Izbrana možnost: »Ena tabela `Charge`«, ker ima najemnik en obračun in eno stanje dolga, ne glede na izvor zneska. Vsaka obveznost ima vrsto, rok plačila, znesek in datum plačila, prek neobveznega tujega ključa pa je povezana na izvor (`BillShare`, `Booking` ali `RepairRequest`). Zamude, označevanje plačil in mesečni obračun so tako izvedeni enkrat za vse vrste.

### Posledice

* Dobro, ker obračun, stanje dolga in seznam zamud nastanejo z eno preprosto poizvedbo.
* Dobro, ker je za označevanje plačila potrebna ena sama končna točka (`POST /api/charges/{id}/mark-paid`).
* Dobro, ker nova vrsta zneska (na primer poraba po števcih) pomeni novo vrednost vrste in ne nove tabele z enako logiko.
* Slabo, ker ima tabela več neobveznih tujih ključev, od katerih je izpolnjen največ eden, kar je treba zagotoviti z omejitvijo CHECK.
* Slabo, ker podrobnosti posamezne vrste (na primer način delitve položnice) ostanejo v izvorni tabeli, zato je za prikaz podrobnosti potreben dodaten stik.
* Slabo, ker izdane obveznosti ni dovoljeno spreminjati, zato je popravek vedno nov zapis, kar poveča število vrstic.

## Prednosti in slabosti možnosti

### Ena tabela `Charge`

* Dobro, ker je vsa logika plačil na enem mestu.
* Slabo, ker je povezava na izvor manj stroga kot pri ločenih tabelah.

### Ločene tabele po vrsti

* Dobro, ker ima vsaka vrsta točno svoja polja.
* Slabo, ker je treba roke, plačila in zamude obravnavati štirikrat, obračun pa sestaviti iz štirih poizvedb.

### Ločene tabele s pogledom

* Dobro, ker ohrani ločene tabele in hkrati omogoči skupen prikaz.
* Slabo, ker pogled podatke samo bere. Označevanje plačil in poslovna pravila ostanejo razpršena po štirih tabelah.

## Dodatne informacije

* Funkcionalnost F2 (mesečne obveznosti): [02-funkcionalnosti-in-obseg.md](../02-funkcionalnosti-in-obseg.md)
* Povezano: [ADR-0007](0007-zneski-v-centih.md), [ADR-0009](0009-locena-entiteta-leasetenant.md), [ADR-0013](0013-shranjena-cena-rezervacije.md)
