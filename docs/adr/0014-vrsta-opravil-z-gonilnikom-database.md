# Vrsta opravil z gonilnikom `database`

## Kontekst in opis problema

Nekatera opravila ne smejo upočasniti odgovora API-ja ali pa se izvajajo ob določenem času: pošiljanje e-pošte (povabila, opozorila), mesečno ustvarjanje obveznosti, opozorila pred iztekom najema in zamudami ter zaključevanje preteklih rezervacij. Rešitev je namenjena stavbam s 5 do 50 stanovanji, zato je opravil malo. Odločiti se je treba, kje so opravila shranjena, dokler jih delavec ne izvede.

## Obravnavane možnosti

* Laravel Queue z gonilnikom `database` (PostgreSQL)
* Laravel Queue z Redisom
* Brez vrste: sinhrono izvajanje v zahtevi in samo razporejevalnik

## Odločitev

Izbrana možnost: »Gonilnik `database`«, ker je to ena storitev manj za zagon in vzdrževanje, obremenitev pa je majhna. Opravila so v isti bazi kot ostali podatki, zato jih je mogoče vpisati v isti transakciji kot spremembo, ki jih sproži. Zamenjava gonilnika z Redisom je kasneje stvar nastavitve, ne spremembe kode.

### Posledice

* Dobro, ker lokalno in v produkciji ni treba nameščati, varnostno kopirati in nadzirati še ene storitve.
* Dobro, ker se opravilo ne zapiše, če se transakcija, ki ga je sprožila, prekliče.
* Dobro, ker so čakajoča in neuspela opravila vidna z navadno poizvedbo SQL.
* Slabo, ker delavec bazo periodično sprašuje po novih opravilih, kar jo dodatno obremeni.
* Slabo, ker gonilnik ni primeren za veliko število opravil ali zelo kratke zakasnitve.
* Slabo, ker je še vedno potreben ločen proces delavca (`queue:work`, `schedule:work`).
