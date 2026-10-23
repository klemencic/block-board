# Preprečevanje prekrivanja rezervacij z izključitveno omejitvijo v bazi

## Kontekst in opis problema

Pri rezervaciji skupnih prostorov (F4) se dva termina istega prostora ne smeta prekrivati. Najbolj iskane termine (večeri, vikendi) bo pogosto hkrati izbralo več najemnikov. Preverjanje, ki najprej poišče prekrivajoče se rezervacije in nato vpiše novo, odpove, ko dve zahtevi prispeta hkrati: obe preverjanje uspešno prestaneta in obe rezervaciji se zapišeta.

## Obravnavane možnosti

* Izključitvena omejitev v bazi
* Preverjanje samo v aplikaciji
* Preverjanje v aplikaciji z zaklepanjem (`SELECT … FOR UPDATE` ali svetovalno zaklepanje)

## Odločitev

Izbrana možnost: »Izključitvena omejitev v bazi«, ker edina onemogoči prekrivanje v vsakem primeru, tudi pri hkratnih zahtevah in pri vpisih, ki ne gredo skozi `BookingService`. Omejitev velja nad prostorom in časovnim razponom termina in ne upošteva preklicanih rezervacij. `BookingService` prekrivanje še vedno preveri vnaprej, da lahko uporabniku vrne razumljivo sporočilo, kršitev omejitve pa API pretvori v odgovor `409`.

### Posledice

* Dobro, ker je dvojna rezervacija nemogoča, ne glede na število hkratnih zahtev.
* Dobro, ker pravilo velja za vse poti vpisa (API, delavec v ozadju, polnjenje podatkov, ročni popravki).
* Dobro, ker ni potrebno zaklepanje v aplikaciji, ki bi bilo lahko napačno izvedeno ali pozabljeno.
* Slabo, ker omejitev zahteva razširitev `btree_gist` in migracijo s SQL, specifičnim za PostgreSQL.
* Slabo, ker je sporočilo baze ob kršitvi splošno, zato ga mora API prepoznati in prevesti v uporabniku razumljiv odgovor.
* Slabo, ker tedenske omejitve ur na stanovanje ni mogoče zapisati kot omejitev v bazi in ostaja v aplikaciji.
