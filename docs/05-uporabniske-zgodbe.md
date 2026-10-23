# 05 – Uporabniške zgodbe

## Pregled zgodb

| Oznaka | Zgodba | Funkcionalnost | Vloga |
| --- | --- | --- | --- |
| US-01 | Ustvarjanje in aktivacija najema | F1 | Lastnik stavbe |
| US-02 | Odpoved najema | F1 | Najemnik |
| US-03 | Samodejne mesečne obveznosti za najemnino | F2 | Lastnik stavbe |
| US-04 | Pregled lastnih obveznosti | F2 | Najemnik |
| US-05 | Označevanje plačil in pregled zamud | F2 | Lastnik stavbe |
| US-06 | Delitev položnice stanovanja po dnevih bivanja | F3 | Lastnik stavbe |
| US-07 | Delitev položnice stavbe med stanovanja | F3 | Lastnik stavbe |
| US-08 | Rezervacija skupnega prostora | F4 | Najemnik |
| US-09 | Prijava okvare | F7 | Najemnik |
| US-10 | Potrditev prijave, plačnik in izvajalec | F7 | Lastnik stavbe |
| US-11 | Izvedba dodeljenega popravila | F7 | Izvajalec del |
| US-12 | Zaključek prijave in obračun popravila | F7 | Lastnik stavbe |
| US-13 | Pregled stavbe | F8 | Lastnik stavbe |
| US-14 | Objava obvestila | F9 | Lastnik stavbe |
| US-15 | Pregled obveščenosti stanovanj | F9 | Lastnik stavbe |

## F1 – Najem z življenjskim ciklom

### US-01 – Ustvarjanje in aktivacija najema

- **Funkcionalnost:** F1 – Najem z življenjskim ciklom
- **Vloga:** Lastnik stavbe

**Zgodba:** Kot lastnik stavbe želim za stanovanje ustvariti in aktivirati najem z najemniki in njihovimi deleži najemnine, da so vsi dogovorjeni pogoji zapisani na enem mestu in da sistem začne ustvarjati mesečne obveznosti.

**Acceptance criteria:**

1. Obrazec zahteva datum začetka, mesečno najemnino, varščino, odpovedni rok v dneh in vsaj enega najemnika z imenom, e-naslovom in deležem najemnine. Datum konca je neobvezen; če ni vpisan, je najem sklenjen za nedoločen čas.
2. Če vsota deležev ni enaka mesečni najemnini, se najem ne shrani, ob polju pa se izpiše razlika, na primer »Deleži so za 50,00 € nižji od najemnine.«
3. Najemnik, ki še nima računa, prejme e-poštno povabilo s povezavo za nastavitev gesla, ki velja 7 dni. Uporabnik, ki račun z istim e-naslovom že ima, ne dobi novega računa, ampak samo dostop do tega najema.
4. Shranjen najem ima stanje »osnutek« in zanj sistem ne ustvari nobene obveznosti. Z gumbom »Aktiviraj« ga lastnik prestavi v stanje »aktiven«, stanovanje pa je v pregledu stavbe prikazano kot zasedeno.
5. Če ima stanovanje že najem v stanju »aktiven« ali »v odpovednem roku«, se aktivacija ne izvede in prikaže se sporočilo »Stanovanje že ima aktiven najem.« s povezavo na ta najem.

### US-02 – Odpoved najema

- **Funkcionalnost:** F1 – Najem z življenjskim ciklom
- **Vloga:** Najemnik

**Zgodba:** Kot najemnik želim podati odpoved najema, da sistem izračuna datum prenehanja in obe strani vesta, do kdaj najem traja.

**Acceptance criteria:**

1. Odpoved je mogoča samo pri najemu v stanju »aktiven« in velja za celoten najem.
2. Pri najemu z več sostanovalci morajo odpoved potrditi vsi. Dokler je ne potrdijo, najem ostane aktiven, sostanovalci pa vidijo, kdo odpovedi še ni potrdil. Datum odpovedi je dan zadnje potrditve.
3. Datum prenehanja je datum odpovedi, povečan za odpovedni rok v dneh: odpoved 1. 3. 2027 z rokom 30 dni pomeni prenehanje 31. 3. 2027. Najemnik ta datum vidi pred oddajo odpovedi. Pri najemu za določen čas datum prenehanja ni nikoli po datumu konca najema.
4. Ko odpoved velja, najem preide v stanje »v odpovednem roku«, lastnik in vsi sostanovalci pa prejmejo e-pošto z datumom odpovedi in datumom prenehanja.
5. Na dan prenehanja, pri najemu za določen čas brez odpovedi pa na datum konca, sistem najem prestavi v stanje »zaključen«, za naslednji mesec ne ustvari več obveznosti za najemnino, stanovanje pa v pregledu stavbe označi kot prosto.

## F2 – Mesečne obveznosti

### US-03 – Samodejne mesečne obveznosti za najemnino

- **Funkcionalnost:** F2 – Mesečne obveznosti
- **Vloga:** Lastnik stavbe

**Zgodba:** Kot lastnik stavbe želim, da sistem vsak mesec samodejno ustvari obveznost za najemnino vsakega najemnika, da mi mesečnega obračuna ni treba pripravljati ročno.

**Acceptance criteria:**

1. Prvi dan v mesecu sistem za vsakega najemnika, ki živi v stanovanju z najemom v stanju »aktiven« ali »v odpovednem roku«, ustvari obveznost vrste »najemnina« v višini njegovega deleža. Za osnutke, zaključene najeme, najeme z začetkom v prihodnosti in izseljene sostanovalce obveznosti ne ustvari.
2. Za najem, ki se začne med mesecem, sistem na dan začetka ustvari obveznost za sorazmerni del najemnine. Pri deležu 300,00 € in začetku 16. 5. 2027 je obveznost za maj 16/31 × 300,00 € = 154,84 €.
3. Rok plačila je dan v mesecu, določen v nastavitvah stavbe. Če je nastavljen 8. dan, je rok za majsko najemnino 8. 5. 2027.
4. Vsak najemnik ima za posamezen mesec največ eno obveznost za najemnino, tudi če se ustvarjanje obveznosti izvede večkrat.
5. Vsak najemnik prvi dan v mesecu prejme e-pošto s seznamom obveznosti za ta mesec (najemnina in vse obveznosti, nastale v preteklem mesecu), skupnim zneskom in rokom plačila.

### US-04 – Pregled lastnih obveznosti

- **Funkcionalnost:** F2 – Mesečne obveznosti
- **Vloga:** Najemnik

**Zgodba:** Kot najemnik želim videti vse svoje obveznosti po mesecih in izvor vsakega zneska, da vem, koliko in do kdaj moram plačati.

**Acceptance criteria:**

1. Stran »Moje obveznosti« prikaže obveznosti, združene po mesecu obračuna. Vsaka vrstica vsebuje vrsto (najemnina, stroški, rezervacija, popravilo, drugo), opis, znesek, rok plačila in stanje (odprto, plačano, zamuja).
2. Za vsak mesec sta prikazani vsota vseh obveznosti in vsota še odprtih obveznosti.
3. Neplačana obveznost s pretečenim rokom ima oznako »Zamuja« in število dni zamude.
4. Klik na obveznost vrste stroški, rezervacija ali popravilo odpre njen izvor: položnico z izračunom deleža, rezervacijo ali prijavo okvare.
5. Najemnik vidi samo svoje obveznosti, obveznosti drugih najemnikov in sostanovalcev pa ne.

### US-05 – Označevanje plačil in pregled zamud

- **Funkcionalnost:** F2 – Mesečne obveznosti
- **Vloga:** Lastnik stavbe

**Zgodba:** Kot lastnik stavbe želim označiti prejeta plačila in videti zamujene obveznosti, da vem, kdo mi dolguje in koliko.

**Acceptance criteria:**

1. Pri odprti obveznosti lastnik izbere »Označi kot plačano« in vnese datum plačila, ki je privzeto današnji in ne sme biti v prihodnosti. Obveznost dobi stanje »plačano«.
2. Pri plačani obveznosti gumb »Označi kot plačano« ni več prikazan.
3. Zneska, opisa in roka izdane obveznosti ni mogoče urejati ali izbrisati. Popravek lastnik vnese kot novo obveznost vrste »drugo« z opisom, negativen znesek pa pomeni dobropis.
4. Seznam obveznosti stavbe je mogoče filtrirati po mesecu, stanovanju in stanju. Filter »Zamuja« prikaže samo neplačane obveznosti s pretečenim rokom.
5. Najemnik plačila ne more označiti, gumb »Označi kot plačano« mu ni prikazan.

## F3 – Delitev položnic

### US-06 – Delitev položnice stanovanja po dnevih bivanja

- **Funkcionalnost:** F3 – Delitev položnic
- **Vloga:** Lastnik stavbe

**Zgodba:** Kot lastnik stavbe želim vnesti položnico za posamezno stanovanje, da jo sistem razdeli med sostanovalce glede na število dni bivanja in mi deležev ni treba računati ročno.

**Acceptance criteria:**

1. Obrazec zahteva vrsto položnice (elektrika, voda, ogrevanje, odpadki, internet, drugo), znesek ter začetek in konec obdobja. Konec obdobja ne sme biti pred začetkom.
2. Vsak sostanovalec dobi delež, sorazmeren številu svojih dni bivanja v obdobju, pri čemer se štejeta tudi dan vselitve in dan izselitve. Položnica 90,00 € za 30 dni, pri kateri sta dva sostanovalca v stanovanju živela 30 dni in ena sostanovalka 15 dni, se razdeli na 36,00 €, 36,00 € in 18,00 €.
3. Vsota deležev je do centa enaka znesku položnice. Ostanek centov pri zaokroževanju dobijo sostanovalci z največ dnevi bivanja, pri enakem številu dni pa tisti, ki je bil na najem dodan prvi, zato se 100,00 € med tri sostanovalce z enakim številom dni razdeli na 33,34 €, 33,33 € in 33,33 €.
4. Pred shranjevanjem lastnik vidi predogled deležev. Po shranjevanju se deleži dodajo kot obveznosti k obračunu naslednjega meseca. Če shranjevanje ne uspe, se ne shrani niti položnica niti noben delež.
5. Če v obdobju v stanovanju ni živel nihče, celoten znesek ostane lastniku in sistem ne ustvari nobene obveznosti.

### US-07 – Delitev položnice stavbe med stanovanja

- **Funkcionalnost:** F3 – Delitev položnic
- **Vloga:** Lastnik stavbe

**Zgodba:** Kot lastnik stavbe želim položnico za celo stavbo razdeliti med stanovanja po površini, številu oseb ali na enake dele, da vsako stanovanje plača svoj delež skupnega stroška.

**Acceptance criteria:**

1. Lastnik izbere enega od treh načinov delitve: po površini (sorazmerno s kvadraturo), po številu oseb (sorazmerno z dnevi bivanja vseh najemnikov stanovanja v obdobju) ali na enake dele.
2. Pri delitvi 300,00 € po površini med stanovanja s 50 m², 30 m² in 20 m² dobijo stanovanja 150,00 €, 90,00 € in 60,00 €.
3. Delež stanovanja, v katerem v obdobju ni živel nihče, se zapiše kot delež lastnika in ne postane obveznost nobenega najemnika.
4. Delež vsakega stanovanja se med sostanovalce razdeli po dnevih bivanja kot v US-06. Vsota vseh deležev je do centa enaka znesku položnice.
5. Najemnik ob svoji obveznosti vidi skupni znesek položnice, način delitve, delež svojega stanovanja in svoje dni bivanja, deležev drugih stanovanj pa ne vidi.

## F4 – Rezervacija skupnih prostorov

### US-08 – Rezervacija skupnega prostora

- **Funkcionalnost:** F4 – Rezervacija skupnih prostorov
- **Vloga:** Najemnik

**Zgodba:** Kot najemnik želim videti proste termine skupnega prostora in rezervirati termin, da prostor lahko uporabim brez dogovarjanja z drugimi najemniki.

**Acceptance criteria:**

1. Koledar prostora prikaže tedenski pregled v obratovalnem času, zasedeni termini so označeni z »Zasedeno« brez imen drugih najemnikov.
2. Rezervacija se začne in konča ob polni ali pol ure, je v celoti znotraj obratovalnega časa in se ne začne v preteklosti.
3. Če se termin prekriva z drugo nepreklicano rezervacijo istega prostora, sistem rezervacijo zavrne s sporočilom »Termin je že zaseden.« Če dva najemnika isti termin rezervirata hkrati, uspe samo ena rezervacija.
4. Če bi stanovanje s to rezervacijo preseglo tedensko omejitev ur, sistem rezervacijo zavrne in izpiše, koliko ur je še na voljo. Upoštevajo se rezervacije vseh sostanovalcev v koledarskem tednu od ponedeljka do nedelje.
5. Cena (trajanje × cena na uro) se shrani ob rezervaciji in se doda kot obveznost k obračunu meseca po terminu: 3 ure po 5,00 € v aprilu pomenijo obveznost 15,00 € v majskem obračunu. Pri brezplačnem prostoru obveznost ne nastane.

## F7 – Prijave okvar s pravilom plačnika

Prijava okvare prehaja skozi stanja prijavljeno, potrjeno, zavrnjeno, dodeljeno, dogovorjen termin, opravljeno in zaključeno. Zgodbe US-09 do US-12 pokrivajo korake posameznih vlog.

### US-09 – Prijava okvare

- **Funkcionalnost:** F7 – Prijave okvar s pravilom plačnika
- **Vloga:** Najemnik

**Zgodba:** Kot najemnik želim prijaviti okvaro s fotografijo, da lastnik dobi vse podatke naenkrat in da je že ob prijavi jasno, kdo praviloma krije strošek.

**Acceptance criteria:**

1. Obvezna polja so naslov, opis, vrsta okvare in lokacija. Za lokacijo lahko najemnik izbere samo stanovanje, v katerem ima aktiven najem, ali skupni prostor stavbe.
2. Priloži lahko do 5 fotografij v obliki JPG ali PNG, vsako do 10 MB. Večje datoteke ali datoteke druge oblike so zavrnjene s sporočilom, ki navede razlog.
3. Po izbiri vrste okvare obrazec prikaže privzetega plačnika, na primer »Ogrevanje in topla voda – krije lastnik«.
4. Shranjena prijava ima stanje »prijavljeno«, lastnik pa prejme e-pošto s povezavo na prijavo.
5. Najemnik vidi seznam svojih prijav in prijav za svoje stanovanje s trenutnim stanjem in časovnico korakov, v kateri je pri vsakem koraku navedeno, kdo ga je opravil in kdaj.

### US-10 – Potrditev prijave, plačnik in izvajalec

- **Funkcionalnost:** F7 – Prijave okvar s pravilom plačnika
- **Vloga:** Lastnik stavbe

**Zgodba:** Kot lastnik stavbe želim prijavo okvare potrditi ali zavrniti ter določiti plačnika in izvajalca, da je za vsako okvaro jasno, kdo jo bo odpravil in kdo jo plača.

**Acceptance criteria:**

1. Prijavo v stanju »prijavljeno« lastnik potrdi ali zavrne. Pri zavrnitvi je obrazložitev obvezna in jo najemnik vidi v časovnici.
2. Lastnik lahko spremeni privzetega plačnika. Sprememba se zabeleži v časovnici s prejšnjo in novo vrednostjo, avtorjem in časom.
3. Izvajalca lastnik izbere med izvajalci, povabljenimi v stavbo. Prijava preide v stanje »dodeljeno«, izvajalec pa prejme e-pošto s povezavo na nalog.
4. Izvajalca je mogoče dodeliti samo potrjeni prijavi. Pri prijavi v stanju »prijavljeno« ali »zavrnjeno« izbira izvajalca ni prikazana.
5. Najemnik, ki je okvaro prijavil, ob vsaki spremembi stanja prejme e-poštno obvestilo.

### US-11 – Izvedba dodeljenega popravila

- **Funkcionalnost:** F7 – Prijave okvar s pravilom plačnika
- **Vloga:** Izvajalec del

**Zgodba:** Kot izvajalec del želim videti naloge, ki so mi dodeljeni, sporočiti termin izvedbe in poročati o opravljenem delu, da najemnik ve, kdaj bom prišel, lastnik pa lahko preveri izvedbo in obračuna strošek.

**Acceptance criteria:**

1. Izvajalec vidi samo prijave, ki so dodeljene njemu, z opisom, fotografijami, vrsto okvare, naslovom stavbe, oznako stanovanja ali skupnega prostora ter imenom in telefonom prijavitelja. Drugih prijav in podatkov o najemu ne vidi.
2. Termin izvedbe mora biti v prihodnosti. Ob vnosu prijava preide v stanje »dogovorjen termin«, najemnik in lastnik pa prejmeta e-pošto s terminom.
3. Kot opravljen je mogoče označiti samo nalog v stanju »dogovorjen termin«. Strošek v evrih je obvezen in ne sme biti negativen, obvezna je tudi vsaj ena fotografija v obliki JPG ali PNG do 10 MB.
4. Po oddaji poročila prijava preide v stanje »opravljeno«, lastnik pa prejme e-pošto.
5. Po oddaji izvajalec stroška in fotografij ne more več spreminjati, razen če mu lastnik prijavo vrne v popravek (US-12).

### US-12 – Zaključek prijave in obračun popravila

- **Funkcionalnost:** F7 – Prijave okvar s pravilom plačnika
- **Vloga:** Lastnik stavbe

**Zgodba:** Kot lastnik stavbe želim pregledati izvedbo popravila in prijavo zaključiti ali vrniti v popravek, da se zaprejo samo res odpravljene okvare in da se strošek pravilno obračuna.

**Acceptance criteria:**

1. Pri prijavi v stanju »opravljeno« lastnik vidi strošek in fotografije izvajalca ter izbere »Zaključi« ali »Vrni v popravek«.
2. Vrnitev v popravek zahteva obrazložitev. Prijava preide nazaj v stanje »dogovorjen termin«, izvajalec pa prejme e-pošto z obrazložitvijo.
3. Če je plačnik najemnik, lastnik ob zaključku izbere najemnika, ki strošek plača. Privzeto je izbran prijavitelj, če je okvaro prijavil najemnik. Izbranemu najemniku sistem doda obveznost vrste »popravilo« v višini stroška k obračunu naslednjega meseca.
4. Če je plačnik lastnik, obveznost ne nastane, strošek pa ostane viden v podrobnostih prijave.
5. Zaključene prijave ni več mogoče spreminjati. Časovnica prikaže vse korake od prijave do zaključka z avtorjem in časom.

## F8 – Register najemnikov in pregled stavbe

### US-13 – Pregled stavbe

- **Funkcionalnost:** F8 – Register najemnikov in pregled stavbe
- **Vloga:** Lastnik stavbe

**Zgodba:** Kot lastnik stavbe želim na eni strani videti stanje vseh stanovanj, da hitro ugotovim, kje moram ukrepati.

**Acceptance criteria:**

1. Tabela prikaže vsa stanovanja stavbe z oznako, nadstropjem, stanjem (prosto, zasedeno, v odpovednem roku), imeni najemnikov ter datumom konca ali prenehanja najema.
2. Pri vsakem stanovanju sta prikazana vsota zamujenih obveznosti in število odprtih prijav okvar, torej prijav, ki niso zaključene ali zavrnjene.
3. Najem, ki se konča v naslednjih 60 dneh, ima oznako »Izteka se«. Stanovanje z zamujenimi obveznostmi je označeno z rdečo barvo.
4. Na vrhu strani so prikazani število zasedenih in vseh stanovanj, skupni znesek zamud in število odprtih prijav okvar.
5. Tabelo je mogoče filtrirati po stanju stanovanja in razvrstiti po znesku zamud. Klik na vrstico odpre podrobnosti stanovanja.

## F9 – Novice in obvestila

### US-14 – Objava obvestila

- **Funkcionalnost:** F9 – Novice in obvestila
- **Vloga:** Lastnik stavbe

**Zgodba:** Kot lastnik stavbe želim objaviti obvestilo za celo stavbo ali posamezno nadstropje, da najemniki pravočasno izvejo za zaporo vode, dela ali spremembe hišnega reda.

**Acceptance criteria:**

1. Obvezna polja so naslov, besedilo in prejemniki: cela stavba ali eno izbrano nadstropje.
2. Obvestilo za nadstropje vidijo samo najemniki stanovanj v tem nadstropju.
3. Ob objavi vsi najemniki, ki jim je obvestilo namenjeno, prejmejo e-pošto z naslovom obvestila in povezavo nanj.
4. Obvestila so pri najemnikih razvrščena od najnovejšega do najstarejšega, pri vsakem pa sta prikazana datum in čas objave.

### US-15 – Pregled obveščenosti stanovanj

- **Funkcionalnost:** F9 – Novice in obvestila
- **Vloga:** Lastnik stavbe

**Zgodba:** Kot lastnik stavbe želim videti, koliko stanovanj je potrdilo branje obvestila, da vem, katera stanovanja moram obvestiti osebno.

**Acceptance criteria:**

1. Najemnik pri vsakem obvestilu vidi gumb »Potrjujem, da sem prebral«. Sistem ob kliku zabeleži čas potrditve, ponovni klik pa ne ustvari novega zapisa.
2. Pri vsakem obvestilu lastnik vidi razmerje obveščenih stanovanj, na primer »7 od 12 stanovanj«. Stanovanje šteje kot obveščeno, ko branje potrdi vsaj en njegov najemnik.
3. Klik na razmerje prikaže seznam stanovanj, ki branja še niso potrdila, z imeni in telefonskimi številkami najemnikov.
4. Pri obvestilu za nadstropje se upoštevajo samo stanovanja v tem nadstropju, prazna stanovanja pa se ne štejejo med prejemnike.
