import { parseKaspi }   from './kaspi.js'
import { parseFreedom } from './freedom.js'

/**
 * Определяет банк по имени файла и тексту, запускает нужный парсер.
 *
 * Для Kaspi: принимает text (строка из pdfjs) — парсим в браузере.
 * Для Freedom: принимает file (File объект) — отправляем на Python сервис.
 *
 * Возвращает { bank, name, period, summary, breakdown, transactions }
 * Бросает 'UNKNOWN_BANK' если банк не распознан.
 */
export async function detectAndParse(filename, text, file) {
  const fn = filename.toLowerCase()

  // Kaspi — парсим текст в браузере как раньше
  if (
    fn.includes('gold_statement') ||
    fn.includes('kaspi') ||
    text.includes('Kaspi Bank') ||
    text.includes('CASPKZKA')
  ) {
    return { bank: 'kaspi', ...parseKaspi(text) }
  }

  // Freedom Bank — отправляем PDF байты на Python сервис
  if (
    fn.includes('freedom') ||
    fn.includes('ffin') ||
    fn.includes('super_card') ||
    text.includes('Фридом Банк') ||
    text.includes('Freedom Bank') ||
    text.includes('bankffin.kz') ||
    text.includes('KSNVKZKA')
  ) {
    return await parseFreedom(file)
  }

  // Неизвестный банк → fallback на AI (в HomeView.vue)
  throw new Error('UNKNOWN_BANK')
}