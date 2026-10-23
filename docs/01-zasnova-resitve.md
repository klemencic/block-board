# 01 – Zasnova rešitve

## Ime rešitve

**BlockBoard** – spletna aplikacija za vodenje najemniške stavbe: najemi, mesečni stroški, skupni prostori in popravila na enem mestu.

## Namen rešitve in problem

Rešitev je namenjena stavbam, v katerih ima vsa stanovanja en lastnik in jih oddaja v najem. To so na primer manjši najemniški bloki, hiše z več stanovanji in študentske stavbe, kjer si stanovanje deli več sostanovalcev.

Lastnik take stavbe ima opravka z desetinami najemnikov, najemnik pa z enim lastnikom, ki mu plačuje najemnino, stroške in varščino. Večina tega razmerja danes poteka prek sporočil, bančnih izpiskov, fotografij v telefonu in preglednic:

- lastnik vsak mesec ročno preverja, kdo je plačal najemnino in kdo zamuja;
- položnice za elektriko, vodo in ogrevanje je treba razdeliti med stanovanja in sostanovalce, kar se zaplete, ko se kdo vseli ali izseli sredi meseca;
- nihče nima ažurnega pregleda, kdo živi v katerem stanovanju in kdaj se kateri najem izteče;
- stanje stanovanja ob vselitvi je zabeleženo s fotografijami, ki jih ob izselitvi nihče več ne najde, zato vračilo varščine postane spor brez dokazov;
- skupni prostori (skupna soba, igralnica, pralnica) se rezervirajo z listkom na vratih, prekrivanja in neplačane uporabe so pogosti;
- najemnik okvaro sporoči po telefonu, potem pa ni jasno, kdo jo plača in kdaj bo odpravljena;
- obvestila o zapori vode ali delih visijo na oglasni deski, ki je marsikdo ne prebere.

BlockBoard celotno razmerje vodi na enem mestu, od vselitve do vračila varščine. Lastnik in najemnik vidita iste podatke: kaj je bilo dogovorjeno, kaj je plačano, kakšno je bilo stanje stanovanja in kaj je še odprto.

Rešitev ne nadomešča najemne pogodbe in ne presoja, kdo ima v sporu prav. Zagotavlja pa, da imata obe strani isti, časovno označen zapis dogovorov in stanja.

## Ciljni uporabniki

| Uporabnik | V kakšni situaciji uporablja rešitev | Za kaj |
| --- | --- | --- |
| Lastnik stavbe | Ko odda stanovanje, vsak mesec ob obračunu, ob prijavi okvare in ob izselitvi najemnika | Vodi stanovanja in najeme, ustvarja mesečne obveznosti, deli stroške, določa skupne prostore in cenik, pripravi primopredajni zapisnik, obračuna varščino, objavlja obvestila |
| Najemnik | Ob vselitvi, vsak mesec ob plačilu, ko želi rezervirati skupni prostor, ko opazi okvaro in ob izselitvi | Potrdi stanje stanovanja, vidi svoje obveznosti in delež stroškov, rezervira skupni prostor, prijavi okvaro, potrdi ali izpodbija odbitke od varščine |
| Izvajalec del | Ko ga lastnik določi za popravilo | Vidi nalog za popravilo, sporoči termin in poroča o izvedbi s fotografijami |

Izvajalec del je lahko zunanji obrtnik ali hišnik, ki skrbi za stavbo.

Rešitev je namenjena lastnikom stavb s približno 5 do 50 stanovanji in njihovim najemnikom.

## Kratek opis delovanja

BlockBoard je spletna aplikacija, do katere uporabniki dostopajo z brskalnikom na računalniku ali telefonu. Lastnik ustvari račun in vnese stavbo, stanovanja in skupne prostore. Najemnike in izvajalce v aplikacijo povabi sam. Vsak uporabnik vidi samo tisto, kar se nanaša nanj.

Najem poteka skozi tri faze.

### 1. Vselitev

1. Lastnik za stanovanje ustvari najem: najemnike, mesečno najemnino, varščino, trajanje in odpovedni rok.
2. Lastnik pripravi primopredajni zapisnik. Za vsak prostor in kos opreme zabeleži stanje in doda fotografije.
3. Najemnik zapisnik pregleda, doda pripombe in ga potrdi. Potrjen zapisnik se ne spreminja več.
4. Najem postane aktiven.

### 2. Mesečni cikel

- **Obveznosti.** Sistem vsak mesec samodejno ustvari obveznost za najemnino z rokom plačila. Lastnik označi plačilo, zamujene obveznosti so izpostavljene.
- **Delitev stroškov.** Lastnik vnese položnico za elektriko, vodo ali ogrevanje. Sistem jo razdeli med stanovanja in nato med sostanovalce glede na število dni bivanja v obračunskem obdobju.
- **Skupni prostori.** Najemnik rezervira termin v skupni sobi, igralnici ali pralnici. Sistem prepreči prekrivanje terminov, upošteva omejitev ur na stanovanje in ceno rezervacije doda k naslednjemu mesečnemu obračunu.
- **Okvare.** Najemnik prijavi okvaro s fotografijo. Vrsta okvare določi, kdo praviloma krije strošek. Lastnik prijavo potrdi in določi izvajalca, ta po opravljenem delu poroča o izvedbi.
- **Iztek najema.** Sistem pred iztekom opozori obe strani in izračuna zadnji dan za odpoved.

### 3. Izselitev

1. Ob odpovedi ali izteku najem preide v odpovedni rok.
2. Lastnik pripravi izselitveni zapisnik po istih prostorih in opremi kot ob vselitvi.
3. Sistem zapisnika primerja in pokaže razlike po posameznih postavkah.
4. Lastnik na podlagi razlik predlaga odbitke od varščine. Vsak odbitek je vezan na konkretno razliko.
5. Najemnik vsak odbitek potrdi ali mu ugovarja z obrazložitvijo. Lastnik lahko sporni odbitek spremeni ali umakne.
6. Ko so vsi odbitki usklajeni in obveznosti poravnane, sistem izračuna znesek za vračilo in najem se zaključi.

### Primer

Lastnica ima blok z dvanajstimi stanovanji. Trisobno stanovanje v drugem nadstropju odda trem študentom. Najemnina je 900 € na mesec, vsak plača 300 €, varščina je 800 €. Ob vselitvi skupaj potrdijo zapisnik, v katerem je za vsako sobo zabeleženo stanje sten, tal in pohištva s fotografijami.

Marca se eden od študentov izseli, 16. marca pa se na njegovo mesto vseli nova sostanovalka. Lastnica vnese položnico za elektriko tega stanovanja v znesku 90 € za obdobje 30 dni. Dva sostanovalca sta v stanovanju živela vseh 30 dni, nova sostanovalka 15 dni. Sistem strošek razdeli na 36 €, 36 € in 18 €.

Aprila eden od najemnikov za rojstni dan rezervira skupno sobo za tri ure. Cena je 5 € na uro, zato se mu k majskemu obračunu doda 15 €. Drug najemnik istega termina ne more več rezervirati.

Maja najemnik prijavi, da ne dela bojler. Okvara spada med tiste, ki jih krije lastnik. Lastnica določi vodoinštalaterja, ta sporoči termin in po popravilu naloži fotografijo.

Ob izselitvi sistem pri primerjavi zapisnikov pokaže dve razliki: poškodovana vrata omare in madeže na steni dnevne sobe. Lastnica predlaga odbitka 120 € in 150 €. Najemniki prvega potrdijo, drugemu ugovarjajo, ker so bili madeži deloma vidni že na fotografiji ob vselitvi. Lastnica drugi odbitek zniža na 60 €, najemniki ga potrdijo. Sistem izračuna vračilo varščine: 800 € − 120 € − 60 € = 620 €.

Zneski v primeru so izmišljeni.
