import type { Role } from '@/stores/auth'

export interface NavItem {
  label: string
  to: string
  icon: string
}

/** Meni je odvisen od vloge (docs/06-wireframes.md, Skupna postavitev). */
export const navigation: Record<Role, NavItem[]> = {
  landlord: [
    { label: 'Pregled stavbe', to: '/pregled', icon: 'grid' },
    { label: 'Najemi', to: '/najemi', icon: 'home' },
    { label: 'Obveznosti', to: '/obveznosti', icon: 'receipt' },
    { label: 'Položnice', to: '/poloznice', icon: 'bolt' },
    { label: 'Skupni prostori', to: '/prostori', icon: 'calendar' },
    { label: 'Okvare', to: '/okvare', icon: 'wrench' },
    { label: 'Register najemnikov', to: '/register', icon: 'user' },
    { label: 'Obvestila', to: '/obvestila', icon: 'megaphone' },
  ],
  tenant: [
    { label: 'Moj najem', to: '/moj-najem', icon: 'home' },
    { label: 'Moje obveznosti', to: '/moje-obveznosti', icon: 'receipt' },
    { label: 'Skupni prostori', to: '/prostori', icon: 'calendar' },
    { label: 'Prijave okvar', to: '/okvare', icon: 'wrench' },
    { label: 'Obvestila', to: '/obvestila', icon: 'megaphone' },
  ],
  contractor: [{ label: 'Moji nalogi', to: '/nalogi', icon: 'clipboard' }],
}

export function homeFor(role: Role | null): string {
  return role ? navigation[role][0].to : '/prijava'
}
