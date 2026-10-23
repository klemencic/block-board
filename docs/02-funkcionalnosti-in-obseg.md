# 02 – Funkcionalnosti in obseg

## Uporabniške vloge

Rešitev pozna tri vloge. Vloga je vezana na stavbo, zato je ista oseba lahko v eni stavbi najemnik, v drugi pa izvajalec.

### Lastnik stavbe

Lastnik stavbe, ki stanovanja oddaja v najem.

**Lahko:**

- ureja stavbo, stanovanja, skupne prostore in njihov cenik,
- ustvarja najeme, vabi najemnike in vodi register vseh najemnikov v stavbi,
- pripravi primopredajni zapisnik ob vselitvi in izselitvi,
- vnaša položnice in izbere način delitve,
- označuje plačila obveznosti,
- potrjuje prijave okvar, določi plačnika in izvajalca,
- predlaga, spremeni ali umakne odbitke od varščine,
- objavlja novice in obvestila.

**Nima pravice:**

- spreminjati zapisnika, potem ko ga je najemnik potrdil,
- potrditi zapisnika ali odbitka v imenu najemnika,
- zaključiti najema, dokler ima odbitke, ki jim najemnik ugovarja,
- spreminjati ali brisati že izdanih obveznosti (popravek je nov zapis),
- dostopati do stavb drugih lastnikov.

### Najemnik

Oseba, ki ima v stavbi aktiven najem. V aplikacijo jo povabi lastnik.

**Lahko:**

- vidi svoj najem, obveznosti, delež stroškov in način izračuna,
- pregleda primopredajni zapisnik, doda pripombe in ga potrdi,
- rezervira skupni prostor in rezervacijo prekliče,
- prijavi okvaro in spremlja njeno obravnavo,
- potrdi odbitek od varščine ali mu ugovarja,
- bere novice in potrdi, da jih je prebral.

**Nima pravice:**

- videti najemov, obveznosti in podatkov drugih stanovanj,
- označiti obveznosti kot plačane,
- spreminjati najemnine, cenika ali načina delitve stroškov,
- določiti izvajalca ali plačnika popravila,
- rezervirati prostora prek dovoljene tedenske omejitve.

### Izvajalec del

Zunanji obrtnik ali hišnik, ki ga lastnik določi za popravilo.

**Lahko:**

- vidi naloge za popravilo, ki so mu dodeljeni, z opisom, fotografijami in lokacijo,
- sporoči termin izvedbe,
- označi delo kot opravljeno, vpiše strošek in priloži fotografije.

**Nima pravice:**

- videti drugih prijav okvar v stavbi,
- videti najemov, obveznosti, varščin in osebnih podatkov najemnikov, razen kontakta prijavitelja,
- spremeniti plačnika popravila,
- zaključiti prijave (to stori lastnik).

### Pregled pravic

| Dejanje | Lastnik stavbe | Najemnik | Izvajalec del |
| --- | --- | --- | --- |
| Urejanje stanovanj in skupnih prostorov | da | ne | ne |
| Ustvarjanje in odpoved najema | da | odpoved svojega | ne |
| Register vseh najemnikov | da | ne | ne |
| Pregled obveznosti | vse | samo svoje | ne |
| Označevanje plačil | da | ne | ne |
| Vnos in delitev položnic | da | ne | ne |
| Rezervacija skupnega prostora | ne (vidi vse) | da | ne |
| Priprava zapisnika | da | ne | ne |
| Potrditev zapisnika | ne | da | ne |
| Predlog odbitka od varščine | da | ne | ne |
| Potrditev ali ugovor na odbitek | ne | da | ne |
| Prijava okvare | da | da | ne |
| Določitev plačnika in izvajalca | da | ne | ne |
| Poročilo o izvedbi popravila | ne | ne | da |
| Objava novic | da | ne | ne |

## Predvidene funkcionalnosti

Rešitev predvideva 15 funkcionalnosti. Sedem jih je označenih z **MVP** in sestavljajo prvo delujočo različico. Med njimi so F1, F3, F4 in F7 domensko specifične.

| # | Funkcionalnost | Opis | MVP |
| --- | --- | --- | --- |
| F1 | Najem z življenjskim ciklom | Najem ima najemnike, najemnino, varščino, trajanje in odpovedni rok ter prehaja skozi stanja osnutek, aktiven, v odpovednem roku in zaključen. Sistem ob odpovedi izračuna datum prenehanja, pred iztekom opozori obe strani in ne dovoli dveh aktivnih najemov istega stanovanja hkrati. | **MVP** |
| F2 | Mesečne obveznosti | Sistem vsak mesec samodejno ustvari obveznost za najemnino vsakega najemnika z rokom plačila. Na isti obračun se dodajo deleži položnic, rezervacije skupnih prostorov in popravila v breme najemnika. Lastnik označi plačilo, zamujene obveznosti so izpostavljene. | **MVP** |
| F3 | Delitev položnic | Lastnik vnese položnico za stavbo ali posamezno stanovanje. Sistem jo razdeli med stanovanja po površini, številu oseb ali na enake dele, znotraj stanovanja pa med sostanovalce po številu dni bivanja v obračunskem obdobju. Delež praznega stanovanja ostane lastniku. Vsota deležev je do centa enaka položnici. | **MVP** |
| F4 | Rezervacija skupnih prostorov | Lastnik določi skupne prostore s ceno na uro, obratovalnim časom in tedensko omejitvijo ur na stanovanje. Najemnik rezervira termin, sistem prepreči prekrivanje in ceno doda k naslednjemu obračunu. Preklic je brezplačen do določenega števila ur pred terminom, kasneje se rezervacija zaračuna. | **MVP** |
| F5 | Primopredajni zapisnik s primerjavo | Ob vselitvi se za vsak prostor in kos opreme zabeleži stanje s fotografijami, najemnik zapisnik potrdi in ta se zaklene. Ob izselitvi se pripravi drugi zapisnik po istih postavkah, sistem pa pokaže razlike med njima. | |
| F6 | Obračun varščine | Lastnik predlaga odbitke, vsak je vezan na konkretno razliko iz primerjave zapisnikov. Najemnik odbitek potrdi ali mu ugovarja z obrazložitvijo, lastnik ga lahko spremeni ali umakne. Sistem izračuna znesek za vračilo in dovoli zaključek najema šele, ko ni več spornih odbitkov. | |
| F7 | Prijave okvar s pravilom plačnika | Najemnik prijavi okvaro s fotografijo. Vrsta okvare določi privzetega plačnika (lastnik ali najemnik). Lastnik prijavo potrdi in določi izvajalca, ta sporoči termin in poroča o izvedbi. Če strošek bremeni najemnika, se doda k njegovemu obračunu. | **MVP** |
| F8 | Register najemnikov in pregled stavbe | Lastnik na eni strani vidi vsa stanovanja z zasedenostjo, najemniki, datumi izteka najemov, zamujenimi obveznostmi in odprtimi okvarami. | **MVP** |
| F9 | Novice in obvestila | Lastnik objavlja obvestila za celo stavbo ali posamezno nadstropje (zapora vode, termin del, hišni red). Najemniki potrdijo branje, lastnik vidi, koliko stanovanj je bilo obveščenih. | **MVP** |
| F10 | Popisi števcev in poraba | Najemniki mesečno vnesejo stanje števcev. Sistem prikaže porabo po mesecih in večje odstopanje od običajne porabe označi za pregled. Odčitki se lahko uporabijo kot ključ pri delitvi položnic. | |
| F11 | Opomini in stanje dolga | Sistem po izteku roka plačila pošlje opomin in za vsakega najemnika vodi skupno stanje odprtih obveznosti. | |
| F12 | Inventar in ključi | Seznam pohištva, naprav in ključev, predanih ob vselitvi, ki se ob izselitvi preveri skupaj z zapisnikom. | |
| F13 | Sprememba najemnine | Lastnik napove spremembo najemnine z datumom začetka veljavnosti. Sistem najemnika obvesti vnaprej in novo najemnino uporabi od določenega meseca. | |
| F14 | Dokumenti stanovanja | Najemna pogodba, hišni red, navodila za naprave in energetska izkaznica na enem mestu, dostopni najemnikom tega stanovanja. | |
| F15 | Letni pregled prihodkov in stroškov | Za vsako stanovanje in celotno stavbo: prejete najemnine, stroški popravil, prihodki od skupnih prostorov in dnevi nezasedenosti. | |

### Netrivialen delovni proces

Prva različica vsebuje dva povezana procesa.

**Obravnava okvare (F7)** poteka med vsemi tremi vlogami:

1. Najemnik prijavi okvaro s fotografijo in izbere vrsto okvare. Sistem iz vrste določi privzetega plačnika.
2. Lastnik prijavo potrdi ali zavrne, po potrebi spremeni plačnika in določi izvajalca.
3. Izvajalec sporoči termin izvedbe.
4. Izvajalec delo označi kot opravljeno, vpiše strošek in priloži fotografije.
5. Lastnik izvedbo potrdi in prijavo zaključi ali jo vrne v popravek.
6. Če strošek bremeni najemnika, ga sistem doda k njegovim mesečnim obveznostim.

Sistem dovoli samo veljavne prehode med stanji (prijavljeno, potrjeno, zavrnjeno, dodeljeno, dogovorjen termin, opravljeno, zaključeno) in beleži, kdo je kateri korak opravil in kdaj.

**Najem in mesečni obračun (F1 do F4)** poteka med lastnikom in najemnikom:

1. Lastnik ustvari najem v stanju osnutek, doda najemnike z njihovimi deleži najemnine in najem aktivira.
2. Sistem vsak mesec ustvari obveznost za najemnino vsakega najemnika.
3. Lastnik vnese položnice, sistem jih razdeli po dnevih bivanja in deleže doda k obveznostim.
4. Rezervacije skupnih prostorov se dodajo k obračunu naslednjega meseca.
5. Ena od strani poda odpoved, sistem izračuna datum prenehanja in najem preide v odpovedni rok.
6. Na datum prenehanja se najem zaključi, sistem preneha ustvarjati obveznosti in stanovanje označi kot prosto.

Primopredajni zapisnik (F5) in obračun varščine (F6) bosta ta proces dopolnila v naslednji različici: potrditev vselitvenega zapisnika bo pogoj za aktivacijo najema, uskladitev odbitkov pa pogoj za njegov zaključek.

## Kasnejše nadgradnje

1. **Izvoz obračuna v PDF** s QR kodo za plačilo (UPN QR).
2. **Uvoz bančnih izpiskov** in samodejno povezovanje plačil z obveznostmi.
3. **Vloga upravljavca.** Lastnik pooblasti zaposlenega ali agencijo, da vodi stavbo v njegovem imenu, z omejenimi pravicami.
4. **Čakalna lista za prosta stanovanja.** Ko najem preide v odpovedni rok, se stanovanje ponudi prijavljenim interesentom.
5. **Mobilna uporaba (PWA)** s potisnimi obvestili in fotografiranjem okvare neposredno iz aplikacije.
6. **Samodejni odčitki pametnih števcev** namesto ročnega vnosa.
7. **Načrt rednega vzdrževanja.** Ponavljajoča se opravila (servis peči, pregled dimnika) z opomniki.
8. **Sinhronizacija rezervacij s koledarjem** (iCal) in varščina za rezervacijo prostora za dogodke.
9. **Ocenjevanje izvajalcev** po zaključenem popravilu.
10. **Več stavb in primerjava med njimi** za lastnike z večjim portfeljem.

## Out of scope

V tem projektu ne bodo implementirani:

- **Dejansko plačevanje.** Rešitev ne izvaja nakazil, ne sprejema kartic in ni povezana z bankami. Plačilo se samo označi.
- **Priprava in podpisovanje najemnih pogodb.** Rešitev pogodbe ne sestavlja in ne omogoča pravno veljavnega elektronskega podpisa.
- **Etažna lastnina.** Stavbe z več lastniki, glasovanje lastnikov in rezervni sklad niso podprti.
- **Oglaševanje stanovanj in preverjanje najemnikov.** Rešitev ni oglasnik in ne preverja plačilne sposobnosti.
- **Kratkoročno turistično oddajanje.** Rezervacije stanovanj po dnevih niso podprte.
- **Računovodstvo in davki.** Rešitev ne pripravlja davčnih napovedi od oddajanja.
- **Klepet med najemniki** ali forum.
- **Nadzor dostopa.** Rešitev ne upravlja pametnih ključavnic ali vstopa v skupne prostore.
- **Nativna mobilna aplikacija** za iOS ali Android.
