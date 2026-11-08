import { describe, expect, it } from 'vitest'
import { shareDifferenceMessage } from './shares'

describe('shareDifferenceMessage', () => {
  it('izpiše razliko, ko so deleži nižji od najemnine', () => {
    expect(shareDifferenceMessage(90000, [45000, 40000])).toBe('Deleži so za 50,00 € nižji od najemnine.')
  })

  it('izpiše razliko, ko so deleži višji od najemnine', () => {
    expect(shareDifferenceMessage(50000, [30000, 25000])).toBe('Deleži so za 50,00 € višji od najemnine.')
  })

  it('ne izpiše ničesar, ko se vsota ujema', () => {
    expect(shareDifferenceMessage(90000, [45000, 45000])).toBeNull()
  })
})
