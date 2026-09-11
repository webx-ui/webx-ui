import type { BadgeType, IconName } from '@webx-ui/core'

/**
 * A submission is whatever a form on the site sent us: a few named fields plus the
 * metadata the page collected around them. Nothing here is typed per form — that is
 * the point. A new form on the site means a new row in `forms`, not a new screen.
 */
export interface SubmissionField {
  label: string
  value: string
  /** Renders as a link — a product, a page, an order. */
  link?: boolean
}

export interface SubmissionMeta {
  label: string
  value: string
  link?: boolean
  mono?: boolean
}

export type SubmissionStatus = 'new' | 'progress' | 'done'

export interface Submission {
  id: number
  /** Which form on the site sent it. */
  form: string
  name: string
  initials: string
  phone: string
  email: string
  /** Pre-formatted for the list; `at` carries the exact moment for the tooltip. */
  time: string
  at: string
  ref: string
  unread: boolean
  status: SubmissionStatus
  assignee: string | null
  preview: string
  fields: SubmissionField[]
  meta: SubmissionMeta[]
  log?: { who: string; text: string }
}

export interface FormDef {
  id: string
  label: string
  tone: BadgeType
  icon: IconName
}

/** Seven forms, of which three carry almost everything — the usual shape. */
export const forms: FormDef[] = [
  { id: 'callback', label: 'Зворотний дзвінок', tone: 'primary', icon: 'phone' },
  { id: 'product', label: 'Питання по товару', tone: 'success', icon: 'tag' },
  { id: 'service', label: 'Заявка на сервіс', tone: 'warning', icon: 'settings' },
  { id: 'partner', label: 'Співпраця', tone: 'info', icon: 'users' },
  { id: 'price', label: 'Запит ціни на техніку', tone: 'default', icon: 'file' },
  { id: 'vacancy', label: 'Відгук на вакансію', tone: 'default', icon: 'user' },
  { id: 'review', label: 'Відгук про сервіс', tone: 'default', icon: 'star' },
]

export const submissions: Submission[] = [
  {
    id: 128,
    form: 'callback',
    name: 'Іван Петренко',
    initials: 'ІП',
    phone: '+380 67 123 45 67',
    email: 'i.petrenko@ukr.net',
    time: '14:20',
    at: '2026-07-03 14:20:11',
    ref: '№ 128 · сьогодні 14:20',
    unread: true,
    status: 'new',
    assignee: null,
    preview: 'Передзвоніть після 10:00, цікавить JCB 3CX у наявності',
    fields: [
      { label: 'Зручний час', value: 'Після 10:00, будні' },
      { label: 'Товар', value: 'Екскаватор-навантажувач JCB 3CX', link: true },
      {
        label: 'Коментар',
        value:
          'Передзвоніть після 10:00. Цікавить наявність на складі, умови лізингу та чи є гідромолот у комплекті.',
      },
    ],
    meta: [
      { label: 'Сторінка', value: '/catalog/jcb-3cx', link: true },
      { label: 'Кампанія', value: 'google / cpc / spring-2026' },
      { label: 'Пристрій', value: 'iPhone · Safari' },
      { label: 'IP', value: '31.128.44.17', mono: true },
    ],
  },
  {
    id: 127,
    form: 'product',
    name: 'Олена Коваль',
    initials: 'ОК',
    phone: '+380 50 447 12 03',
    email: 'o.koval@bud.com.ua',
    time: '13:48',
    at: '2026-07-03 13:48:02',
    ref: '№ 127 · сьогодні 13:48',
    unread: true,
    status: 'new',
    assignee: null,
    preview: 'Чи є лізинг на 24 місяці і яка передоплата?',
    fields: [
      { label: 'Товар', value: 'Навантажувач Bobcat S175', link: true },
      {
        label: 'Питання',
        value: 'Чи є лізинг на 24 місяці і яка передоплата? Чи можлива доставка у Черкаси?',
      },
    ],
    meta: [
      { label: 'Сторінка', value: '/catalog/bobcat-s175', link: true },
      { label: 'Кампанія', value: 'прямий захід' },
      { label: 'Пристрій', value: 'Windows · Chrome' },
      { label: 'IP', value: '95.164.12.9', mono: true },
    ],
  },
  {
    id: 124,
    form: 'service',
    name: 'Сергій Бондар',
    initials: 'СБ',
    phone: '+380 63 900 18 44',
    email: 's.bondar@gmail.com',
    time: 'вчора',
    at: '2026-07-02 16:05:47',
    ref: '№ 124 · вчора 16:05',
    unread: false,
    status: 'progress',
    assignee: 'ОМ',
    preview: 'Гідравліка тече, потрібен виїзд у Бровари',
    log: { who: 'ОМ', text: 'Олег М. взяв у роботу · вчора 16:40' },
    fields: [
      { label: 'Техніка', value: 'JCB 3CX, 2019 р., 4 200 мотогодин' },
      { label: 'Проблема', value: 'Тече гідравліка з-під лівого циліндра стріли' },
      { label: 'Адреса', value: 'Бровари, вул. Київська 12' },
    ],
    meta: [
      { label: 'Сторінка', value: '/service', link: true },
      { label: 'Кампанія', value: 'google / organic' },
      { label: 'Пристрій', value: 'Android · Chrome' },
      { label: 'IP', value: '178.92.5.201', mono: true },
    ],
  },
  {
    id: 123,
    form: 'callback',
    name: 'ТОВ «Мостбуд»',
    initials: 'МТ',
    phone: '+380 44 501 22 18',
    email: 'office@mostbud.ua',
    time: 'вчора',
    at: '2026-07-02 11:12:30',
    ref: '№ 123 · вчора 11:12',
    unread: false,
    status: 'new',
    assignee: null,
    preview: 'Прорахуйте оренду на 3 місяці, 2 одиниці',
    fields: [
      { label: 'Компанія', value: 'ТОВ «Мостбуд», ЄДРПОУ 41255013' },
      {
        label: 'Коментар',
        value: 'Прорахуйте оренду на 3 місяці: 2 одиниці, з оператором, обʼєкт у Вишгороді.',
      },
    ],
    meta: [
      { label: 'Сторінка', value: '/orenda', link: true },
      { label: 'Кампанія', value: 'facebook / social' },
      { label: 'Пристрій', value: 'Windows · Firefox' },
      { label: 'IP', value: '193.108.77.4', mono: true },
    ],
  },
  {
    id: 121,
    form: 'service',
    name: 'Марина Дяченко',
    initials: 'МД',
    phone: '+380 97 221 63 70',
    email: 'm.dyachenko@agro.ua',
    time: '2 лип',
    at: '2026-07-02 09:40:15',
    ref: '№ 121 · 2 липня 09:40',
    unread: true,
    status: 'new',
    assignee: null,
    preview: 'Потрібне ТО після 500 мотогодин, коли є вікно?',
    fields: [
      { label: 'Техніка', value: 'Bobcat S175, 2021 р.' },
      { label: 'Питання', value: 'Потрібне ТО після 500 мотогодин. Коли є вільне вікно на виїзд?' },
    ],
    meta: [
      { label: 'Сторінка', value: '/service/to', link: true },
      { label: 'Кампанія', value: 'google / cpc / service' },
      { label: 'Пристрій', value: 'iPhone · Safari' },
      { label: 'IP', value: '46.211.8.130', mono: true },
    ],
  },
  {
    id: 119,
    form: 'partner',
    name: 'Віктор Шевчук',
    initials: 'ВШ',
    phone: '+380 68 112 90 55',
    email: 'v.shevchuk@lvivtech.ua',
    time: '1 лип',
    at: '2026-07-01 14:02:09',
    ref: '№ 119 · 1 липня 14:02',
    unread: false,
    status: 'progress',
    assignee: 'ІК',
    preview: 'Пропозиція дилерства у Львівській області',
    log: { who: 'ІК', text: 'Ірина К. взяла у роботу · 1 липня 15:20' },
    fields: [
      { label: 'Компанія', value: 'ТОВ «ЛьвівТех»' },
      {
        label: 'Пропозиція',
        value: 'Дилерство у Львівській області, є склад 900 м² і сервісна бригада.',
      },
    ],
    meta: [
      { label: 'Сторінка', value: '/partners', link: true },
      { label: 'Кампанія', value: 'прямий захід' },
      { label: 'Пристрій', value: 'macOS · Chrome' },
      { label: 'IP', value: '77.121.44.18', mono: true },
    ],
  },
  {
    id: 120,
    form: 'product',
    name: 'Андрій Лисенко',
    initials: 'АЛ',
    phone: '+380 93 700 41 26',
    email: 'a.lysenko@ukr.net',
    time: '2 лип',
    at: '2026-07-02 10:15:44',
    ref: '№ 120 · 2 липня 10:15',
    unread: false,
    status: 'done',
    assignee: 'ОМ',
    preview: 'Дякую, замовлення оформив',
    log: { who: 'ОМ', text: 'Олег М. закрив звернення · 2 липня 12:30' },
    fields: [
      { label: 'Товар', value: 'Міні-екскаватор Kubota U27-4', link: true },
      { label: 'Питання', value: 'Чи є в наявності ківш 300 мм?' },
    ],
    meta: [
      { label: 'Сторінка', value: '/catalog/kubota-u27', link: true },
      { label: 'Кампанія', value: 'google / organic' },
      { label: 'Пристрій', value: 'Windows · Chrome' },
      { label: 'IP', value: '31.43.19.77', mono: true },
    ],
  },
  {
    id: 118,
    form: 'callback',
    name: 'Ігор Савчук',
    initials: 'ІС',
    phone: '+380 66 318 55 09',
    email: 'i.savchuk@gmail.com',
    time: '1 лип',
    at: '2026-07-01 09:05:12',
    ref: '№ 118 · 1 липня 09:05',
    unread: false,
    status: 'done',
    assignee: 'ІК',
    preview: 'Передзвонили, домовились на перегляд у суботу',
    log: { who: 'ІК', text: 'Ірина К. закрила звернення · 1 липня 09:40' },
    fields: [
      { label: 'Зручний час', value: 'Будь-коли до 18:00' },
      { label: 'Коментар', value: 'Цікавить вживана техніка до 900 тис. грн.' },
    ],
    meta: [
      { label: 'Сторінка', value: '/catalog/vzhyvana', link: true },
      { label: 'Кампанія', value: 'google / cpc / used' },
      { label: 'Пристрій', value: 'Android · Chrome' },
      { label: 'IP', value: '109.87.14.60', mono: true },
    ],
  },
]
