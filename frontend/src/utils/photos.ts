/**
 * Preverjanje fotografij pred nalaganjem (US-09, US-11). Strežnik pravila preveri ponovno.
 * Vrne seznam sporočil z razlogom zavrnitve.
 */
export function photoErrors(files: { name: string; type: string; size: number }[], maxCount = 5, maxMb = 10): string[] {
  const errors: string[] = []
  if (files.length > maxCount) errors.push(`Priložite lahko največ ${maxCount} fotografij.`)
  for (const file of files) {
    if (!['image/jpeg', 'image/png'].includes(file.type)) {
      errors.push(`Datoteka »${file.name}« ni v obliki JPG ali PNG.`)
    } else if (file.size > maxMb * 1024 * 1024) {
      errors.push(`Datoteka »${file.name}« je večja od ${maxMb} MB.`)
    }
  }
  return errors
}
