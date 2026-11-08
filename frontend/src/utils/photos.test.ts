import { describe, expect, it } from 'vitest'
import { photoErrors } from './photos'

const file = (name: string, type: string, mb: number) => ({ name, type, size: mb * 1024 * 1024 })

describe('photoErrors', () => {
  it('sprejme JPG in PNG do 10 MB', () => {
    expect(photoErrors([file('a.jpg', 'image/jpeg', 2), file('b.png', 'image/png', 10)])).toEqual([])
  })

  it('navede razlog zavrnitve', () => {
    expect(photoErrors([file('racun.pdf', 'application/pdf', 1)])).toEqual(['Datoteka »racun.pdf« ni v obliki JPG ali PNG.'])
    expect(photoErrors([file('velika.jpg', 'image/jpeg', 11)])).toEqual(['Datoteka »velika.jpg« je večja od 10 MB.'])
    expect(photoErrors(Array.from({ length: 6 }, (_, i) => file(`${i}.jpg`, 'image/jpeg', 1)))).toContain(
      'Priložite lahko največ 5 fotografij.',
    )
  })
})
