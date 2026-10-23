# Primopredajni zapisnik kot strukturirane postavke

## Kontekst in opis problema

Ob vračilu varščine pogosto pride do spora, ker fotografij in opisa stanja ob vselitvi ni več mogoče najti ali pa se ne ujemajo s stanjem ob izselitvi. BlockBoard mora vselitveni in izselitveni zapisnik shraniti tako, da ju je mogoče samodejno primerjati in pokazati razlike po posameznih delih stanovanja (F5). Odločiti se je treba, v kakšni obliki je zapisnik shranjen.

## Obravnavane možnosti

* Postavke: vrstica za vsak par prostor–predmet s stanjem, opombo in fotografijami
* Prosto besedilo s priloženimi fotografijami
* En dokument PDF ali skupina fotografij

## Odločitev

Izbrana možnost: »Postavke«, ker je samo strukturirane postavke mogoče samodejno primerjati. Izselitveni zapisnik se pripravi po istih postavkah kot vselitveni, `InspectionComparer` pa za vsako postavko primerja stanje. Fotografije so vezane na posamezno postavko, zato je za vsako razliko jasno, kateri dokaz ji pripada.

### Posledice

* Dobro, ker sistem razlike med zapisnikoma pokaže samodejno, po posameznih postavkah.
* Dobro, ker je izselitveni zapisnik vnaprej izpolnjen s postavkami vselitvenega, zato ga je hitreje pripraviti.
* Dobro, ker se lahko odbitek od varščine sklicuje na konkretno postavko.
* Dobro, ker je potrjen zapisnik mogoče zakleniti na ravni postavk in tako preprečiti kasnejše spremembe.
* Slabo, ker je priprava vselitvenega zapisnika za lastnika več dela kot nalaganje nekaj fotografij.
* Slabo, ker je treba določiti lestvico stanj, ki je dovolj preprosta za vnos in dovolj natančna za primerjavo.
* Slabo, ker neobičajnih predmetov vnaprej določena struktura morda ne pokrije, zato mora vsaka postavka omogočati prosto opombo.
