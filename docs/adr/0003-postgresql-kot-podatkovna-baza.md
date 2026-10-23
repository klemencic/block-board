# PostgreSQL kot podatkovna baza

## Kontekst in opis problema

Podatki BlockBoarda so izrazito relacijski (stavba, stanovanje, najem, najemnik, obveznost, položnica, rezervacija) in v veliki meri finančni. Nekatera pravila morajo veljati v vsakem primeru, tudi ob hkratnih zahtevah: stanovanje ima lahko samo en aktiven najem, rezervacije istega prostora se ne smejo prekrivati, deleži položnice in obveznosti pa morajo nastati skupaj ali pa sploh ne. Izbrati je treba bazo, ki ta pravila lahko zagotovi sama.

## Obravnavane možnosti

* PostgreSQL
* MySQL
* MongoDB

## Odločitev

Izbrana možnost: »PostgreSQL«, ker poleg transakcij in tujih ključev podpira izključitvene omejitve nad časovnimi razponi in delne enolične indekse. Prvo potrebujemo za preprečevanje prekrivanja rezervacij, drugo za pravilo enega aktivnega najema na stanovanje. Ista baza lahko hrani tudi seje in vrsto opravil, zato dodatna shramba ni potrebna.

### Posledice

* Dobro, ker baza sama zagotovi ključna pravila, ne glede na to, ali podatke vpisuje API, delavec v ozadju ali razred za polnjenje.
* Dobro, ker so podatki aplikacije, seje in vrsta opravil v eni storitvi, ki jo je treba nameščati, varnostno kopirati in nadzirati.
* Dobro, ker ima Laravel za PostgreSQL polno podporo.
* Slabo, ker izključitvenih omejitev in delnih indeksov ni mogoče zapisati s standardnim graditeljem sheme Laravel, zato del migracij vsebuje SQL, specifičen za PostgreSQL.
* Slabo, ker zaradi tega aplikacija ni prenosljiva na drugo bazo in testi ne morejo teči na SQLite, ampak potrebujejo PostgreSQL.
