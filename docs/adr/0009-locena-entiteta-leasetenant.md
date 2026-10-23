# Najemnik na najemu kot ločena entiteta `LeaseTenant` z datumi

## Kontekst in opis problema

Stanovanje si pogosto deli več sostanovalcev, ki vsak plača svoj del najemnine. Sostanovalci se menjajo sredi najema: eden se izseli, drug se vseli na njegovo mesto, najem pa se nadaljuje. Stroške je treba razdeliti po dnevih bivanja v obračunskem obdobju (90 € za 30 dni se razdeli na 36 €, 36 € in 18 €, če je nova sostanovalka bivala 15 dni). Odločiti se je treba, kako je najemnik povezan z najemom.

## Obravnavane možnosti

* Ločena entiteta `LeaseTenant` z datumi vselitve, izselitve in deležem najemnine
* Najemnik kot polje (tuji ključ) na najemu
* Vmesna tabela najem–uporabnik brez datumov

## Odločitev

Izbrana možnost: »Ločena entiteta `LeaseTenant`«, ker edina podpira več sostanovalcev, menjavo enega od njih brez novega najema in delitev stroškov po dnevih bivanja. Datumi vselitve in izselitve so podlaga za `BillSplitter`, delež najemnine pa za mesečne obveznosti posameznega najemnika.

### Posledice

* Dobro, ker menjava sostanovalca ne zahteva zaključka najema ter novih zapisnikov in varščine.
* Dobro, ker so obveznosti, rezervacije in deleži položnic vezani na konkretnega najemnika v konkretnem obdobju.
* Dobro, ker razredi Policy najemniku dovolijo dostop samo do najemov, na katerih je naveden v `LeaseTenant`.
* Slabo, ker je skoraj vsaka poizvedba za najemnika dodaten stik.
* Slabo, ker je treba preverjati, da so datumi bivanja znotraj trajanja najema in da je vsota deležev enaka celotni najemnini.
