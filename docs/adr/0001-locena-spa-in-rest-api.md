---
status: sprejeto
date: 2026-10-23
---

# Ločena Vue SPA in REST API namesto strežniško izrisanih strani

## Kontekst in opis problema

BlockBoard uporabljajo tri vloge (lastnik, najemnik, izvajalec), ki vidijo različne strani in menije. Več zaslonov je izrazito interaktivnih: tedenski koledar rezervacij s sprotnim izračunom cene in tedenske omejitve, predogled deležev položnice pred shranjevanjem in časovnica prijave okvare. Kasneje je predvidena tudi mobilna aplikacija. Odločiti se je treba, kako sta uporabniški vmesnik in zaledni del povezana in ločena.

## Obravnavane možnosti

* Ločena Vue SPA in REST API
* Laravel z Inertia.js
* Strežniško izrisane strani (Laravel Blade)

## Odločitev

Izbrana možnost: »Ločena Vue SPA in REST API«, ker dobimo dva jasno ločena dela z dokumentiranim vmesnikom. Vsak korak delovnega procesa (odpoved najema, potrditev zapisnika, ugovor na odbitek) je končna točka API-ja z lastno validacijo in preverjanjem pravic, interaktivni zasloni pa so v SPA naravni. Isti API lahko brez sprememb uporablja tudi kasnejša mobilna aplikacija.

### Posledice

* Dobro, ker je API pogodba med deloma sistema, zato je mogoče frontend in backend razvijati in testirati ločeno.
* Dobro, ker SPA ne vsebuje poslovnih pravil: prikaže to, kar vrne API, vsa preverjanja pa ostanejo na enem mestu na strežniku.
* Dobro, ker mobilna aplikacija ali drug odjemalec ne zahteva novega zalednega dela.
* Slabo, ker je na začetku več dela: usmerjanje, skupno stanje, zaščita CSRF in obravnava napak (`422`, `403`, `409`) morajo biti narejeni v SPA.
* Slabo, ker imamo dva projekta z dvema orodjema za gradnjo (Composer in npm) in dvema naboroma odvisnosti.
* Slabo, ker skrivanje gumbov po vlogah v SPA ni varnostni ukrep. Vsako pravilo mora biti vedno preverjeno tudi v API-ju.

## Prednosti in slabosti možnosti

### Ločena Vue SPA in REST API

* Dobro, ker je vmesnik med deloma jasen, dokumentiran in ponovno uporaben.
* Slabo, ker je postavitev zahtevnejša kot pri strežniško izrisanih straneh.

### Laravel z Inertia.js

* Dobro, ker uporablja komponente Vue brez ločenega API-ja in brez ročne obravnave avtentikacije.
* Slabo, ker so strani tesno vezane na krmilnike Laravel in ni javnega API-ja, ki bi ga lahko uporabila mobilna aplikacija.

### Strežniško izrisane strani (Laravel Blade)

* Dobro, ker je to najpreprostejša možnost z najmanj premičnimi deli.
* Slabo, ker koledar rezervacij in predogled deležev kljub temu zahtevata veliko JavaScripta, ki bi bil razpršen po predlogah.
* Slabo, ker ni API-ja za druge odjemalce.

## Dodatne informacije

* Arhitektura in seznam končnih točk: [04-arhitektura-in-tehnoloski-sklad.md](../04-arhitektura-in-tehnoloski-sklad.md)
* Povezano: [ADR-0005](0005-avtentikacija-s-sejnim-piskotkom.md), [ADR-0012](0012-namenske-koncne-tocke-za-prehode-stanj.md)
