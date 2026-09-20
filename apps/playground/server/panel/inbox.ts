import type {
  InboxAdmin,
  InboxField,
  InboxForm,
  InboxStatus,
  SubmissionAttachment,
  SubmissionEvent,
  SubmissionMeta,
  SubmissionValue,
} from '../../../../packages/module-inbox/src/types'

/**
 * The inbox the playground opens on: three forms, four statuses, and a pile of submissions
 * spread over the last three weeks.
 *
 * Enough of them to page through, uneven enough to see what the list does with a long answer
 * beside an empty one, and with today's and yesterday's at the top — which is the whole of
 * what `WxDate` has to get right.
 */

export const admins: InboxAdmin[] = [
  { id: 1, name: 'Анна Ковальчук', email: 'anna@webx-demo.test' },
  { id: 2, name: 'Дмитрий Левченко', email: 'dmytro@webx-demo.test' },
  { id: 3, name: 'Ольга Сидоренко', email: 'olha@webx-demo.test' },
]

export const statuses: InboxStatus[] = [
  {
    id: 1,
    key: 'new',
    title: { ru: 'Новая', en: 'New' },
    color: 'primary',
    is_default: true,
    is_spam: false,
    is_closed: false,
    position: 1,
    submissions_count: 0,
  },
  {
    id: 2,
    key: 'in-progress',
    title: { ru: 'В работе', en: 'In progress' },
    color: 'warning',
    is_default: false,
    is_spam: false,
    is_closed: false,
    position: 2,
    submissions_count: 0,
  },
  {
    id: 3,
    key: 'done',
    title: { ru: 'Закрыта', en: 'Done' },
    color: 'success',
    is_default: false,
    is_spam: false,
    is_closed: true,
    position: 3,
    submissions_count: 0,
  },
  {
    id: 4,
    key: 'spam',
    title: { ru: 'Спам', en: 'Spam' },
    color: 'danger',
    is_default: false,
    is_spam: true,
    is_closed: true,
    position: 4,
    submissions_count: 0,
  },
]

let fieldId = 0

function field(
  formId: number,
  input: Partial<InboxField> & { key: string; type: InboxField['type'] },
): InboxField {
  fieldId += 1

  return {
    id: fieldId,
    form_id: formId,
    name: input.name ?? input.key,
    key: input.key,
    type: input.type,
    title: input.title ?? { ru: input.key, en: input.key },
    placeholder: input.placeholder ?? {},
    help: input.help ?? {},
    options: input.options ?? {},
    is_enabled: input.is_enabled ?? true,
    is_required: input.is_required ?? false,
    is_fullsize: input.is_fullsize ?? false,
    in_table: input.in_table ?? false,
    position: input.position ?? fieldId,
  }
}

export const forms: InboxForm[] = [
  {
    id: 1,
    slug: 'feedback',
    title: { ru: 'Обратная связь', en: 'Feedback' },
    is_enabled: true,
    options: {
      'thank-you.heading': { ru: 'Спасибо!', en: 'Thank you!' },
      'thank-you.text': {
        ru: 'Мы получили заявку и ответим в рабочее время.',
        en: 'We have your request and will answer during work hours.',
      },
      'design.submit-text': { ru: 'Отправить', en: 'Send' },
      recipients: [{ admin_id: 1 }, { email: 'sales@webx-demo.test' }],
      email_field: 'email',
      'antispam.honeypot': true,
      'antispam.min_seconds': 3,
      'antispam.throttle': 5,
      'antispam.captcha': 'off',
    },
    position: 1,
    submissions_count: 0,
    unread_count: 0,
    created_at: '2026-08-14T10:00:00+00:00',
    updated_at: '2026-09-12T08:30:00+00:00',
  },
  {
    id: 2,
    slug: 'callback',
    title: { ru: 'Заказ звонка', en: 'Call me back' },
    is_enabled: true,
    options: {
      'thank-you.heading': { ru: 'Перезвоним', en: 'We will call' },
      recipients: [{ admin_id: 2 }],
      'antispam.honeypot': true,
      'antispam.captcha': 'turnstile',
    },
    position: 2,
    submissions_count: 0,
    unread_count: 0,
    created_at: '2026-08-20T09:00:00+00:00',
    updated_at: '2026-09-02T14:00:00+00:00',
  },
  {
    id: 3,
    slug: 'job',
    title: { ru: 'Отклик на вакансию', en: 'Job application' },
    is_enabled: false,
    options: { recipients: [{ email: 'hr@webx-demo.test' }] },
    position: 3,
    submissions_count: 0,
    unread_count: 0,
    created_at: '2026-09-01T09:00:00+00:00',
    updated_at: '2026-09-01T09:00:00+00:00',
  },
]

export const fields: InboxField[] = [
  field(1, {
    key: 'name',
    type: 'text',
    title: { ru: 'Имя', en: 'Name' },
    placeholder: { ru: 'Как к вам обращаться', en: 'Your name' },
    is_required: true,
    in_table: true,
  }),
  field(1, {
    key: 'email',
    type: 'email',
    title: { ru: 'Почта', en: 'Email' },
    is_required: true,
    in_table: true,
  }),
  field(1, { key: 'phone', type: 'tel', title: { ru: 'Телефон', en: 'Phone' }, in_table: true }),
  field(1, {
    key: 'budget',
    type: 'select',
    title: { ru: 'Бюджет', en: 'Budget' },
    options: {
      choices: [
        { value: 'small', label: { ru: 'до 100 000 ₴', en: 'up to 100k' } },
        { value: 'medium', label: { ru: '100 000 — 300 000 ₴', en: '100k — 300k' } },
        { value: 'large', label: { ru: 'больше 300 000 ₴', en: 'over 300k' } },
      ],
    },
    in_table: true,
  }),
  field(1, {
    key: 'message',
    type: 'textarea',
    title: { ru: 'Сообщение', en: 'Message' },
    help: { ru: 'Расскажите, что нужно сделать.', en: 'Tell us what you need.' },
    options: { rows: 5 },
    is_fullsize: true,
    is_required: true,
  }),
  field(1, {
    key: 'file',
    type: 'file',
    title: { ru: 'Файл', en: 'File' },
    options: { max_size: 8, extensions: ['pdf', 'png', 'jpg', 'docx'] },
  }),
  field(1, {
    key: 'consent',
    type: 'consent',
    title: { ru: 'Согласие', en: 'Consent' },
    options: {
      text: {
        ru: 'Согласен на обработку персональных данных',
        en: 'I agree to the processing of my data',
      },
    },
    is_required: true,
  }),
  field(2, {
    key: 'name',
    type: 'text',
    title: { ru: 'Имя', en: 'Name' },
    is_required: true,
    in_table: true,
  }),
  field(2, {
    key: 'phone',
    type: 'tel',
    title: { ru: 'Телефон', en: 'Phone' },
    is_required: true,
    in_table: true,
  }),
  field(2, {
    key: 'time',
    type: 'select',
    title: { ru: 'Удобное время', en: 'When to call' },
    options: {
      choices: [
        { value: 'morning', label: { ru: 'Утром', en: 'Morning' } },
        { value: 'afternoon', label: { ru: 'Днём', en: 'Afternoon' } },
        { value: 'evening', label: { ru: 'Вечером', en: 'Evening' } },
      ],
    },
    in_table: true,
  }),
  field(3, {
    key: 'name',
    type: 'text',
    title: { ru: 'Имя', en: 'Name' },
    is_required: true,
    in_table: true,
  }),
  field(3, { key: 'cv', type: 'file', title: { ru: 'Резюме', en: 'CV' }, is_required: true }),
]

/** What one submission holds, before it is dressed up as a row or as a page. */
export interface SubmissionRecord {
  id: number
  form_id: number
  status_id: number
  assignee_id: number | null
  is_read: boolean
  source: string
  values: Record<string, string | null>
  files: SubmissionAttachment[]
  meta: SubmissionMeta
  events: SubmissionEvent[]
  notified_at: string | null
  notify_error: string | null
  created_at: string
  updated_at: string
}

const NAMES = [
  'Ирина Мельник',
  'Сергей Бондаренко',
  'Марина Ткаченко',
  'Виктор Гринько',
  'Алина Демченко',
  'Павел Остапчук',
  'Юлия Романюк',
  'Андрей Савченко',
  'Наталья Кравец',
  'Олег Панасюк',
  'Светлана Гуменюк',
  'Роман Дяченко',
  'Катерина Литвин',
  'Максим Волошин',
  'Елена Пасечник',
  'Игорь Шевченко',
  'Вероника Балан',
  'Тарас Гнатюк',
  'Дарья Мороз',
  'Богдан Кушнир',
]

const MESSAGES = [
  'Нужен сайт для стоматологии: пять страниц, запись через форму и интеграция с телефонией.',
  'Добрый день! Есть старый сайт на Joomla, хотим перенести на нормальную админку. Что по срокам?',
  'Подскажите стоимость поддержки — сайт уже есть, нужен человек, который будет отвечать за него.',
  'Интересует магазин с выгрузкой в маркетплейсы. Ассортимент около 1200 позиций, обновляется каждый день из 1С, поэтому важна автоматическая синхронизация и понятный интерфейс для менеджеров.',
  'Хотим лендинг под новый продукт к 10 октября. Дизайн есть, нужна вёрстка и форма заявки.',
  'Здравствуйте. Нужна доработка личного кабинета: авторизация по коду из СМС.',
  'Сколько будет стоить аудит текущего сайта? Скорость упала, позиции тоже.',
  '',
  'Пишу по поводу вакансии frontend-разработчика, отправил резюме на почту.',
  'Нужна помощь с переездом на новый хостинг и настройкой почты на домене.',
]

const PAGES = ['/services/development', '/contacts', '/', '/services', '/about/team']
const SOURCES = ['form', 'form', 'form', 'panel', 'api']

let submissionId = 0

function submission(
  input: Partial<SubmissionRecord> & { form_id: number; created_at: string },
): SubmissionRecord {
  submissionId += 1

  return {
    id: submissionId,
    form_id: input.form_id,
    status_id: input.status_id ?? 1,
    assignee_id: input.assignee_id ?? null,
    is_read: input.is_read ?? false,
    source: input.source ?? 'form',
    values: input.values ?? {},
    files: input.files ?? [],
    meta: input.meta ?? {},
    events: input.events ?? [],
    notified_at: input.notified_at ?? null,
    notify_error: input.notify_error ?? null,
    created_at: input.created_at,
    updated_at: input.updated_at ?? input.created_at,
  }
}

/** Newest first is what the list asks for, so the fixtures are built backwards from today. */
function ago(days: number, hours: number, minutes = 0): string {
  const when = new Date()

  when.setDate(when.getDate() - days)
  when.setHours(hours, minutes, 0, 0)

  return when.toISOString()
}

export const submissions: SubmissionRecord[] = []

for (let index = 0; index < 46; index += 1) {
  const name = NAMES[index % NAMES.length]
  const message = MESSAGES[index % MESSAGES.length]
  const created = ago(Math.floor(index / 2.4), 9 + (index % 9), (index * 13) % 60)
  const statusId = index === 0 || index === 1 ? 1 : [1, 1, 2, 3, 3, 2, 4][index % 7]
  const assigneeId = statusId === 1 ? null : admins[index % admins.length].id

  submissions.push(
    submission({
      form_id: 1,
      created_at: created,
      status_id: statusId,
      assignee_id: assigneeId as number | null,
      is_read: index > 3,
      source: SOURCES[index % SOURCES.length],
      values: {
        name,
        email: `${transliterate(name.split(' ')[0]).toLowerCase()}@example.com`,
        phone:
          index % 3 === 0
            ? null
            : `+380 ${67 + (index % 3)} ${100 + index} 45 ${10 + (index % 80)}`,
        budget: ['small', 'medium', 'large', null][index % 4],
        message,
        consent: '1',
      },
      files:
        index % 9 === 0
          ? [
              {
                id: 500 + index,
                field_id: 6,
                name: `техзадание-${index}.pdf`,
                size: 184320 + index * 2048,
                mime: 'application/pdf',
                url: `/api/cms/inbox/files/${500 + index}`,
              },
            ]
          : [],
      meta: {
        ip: `95.132.${index % 255}.${(index * 7) % 255}`,
        user_agent:
          index % 2 === 0
            ? 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_2 like Mac OS X) AppleWebKit/605.1.15'
            : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/141.0',
        page: PAGES[index % PAGES.length],
        referrer: index % 4 === 0 ? 'https://www.google.com/' : '',
        locale: 'ru',
        utm:
          index % 5 === 0
            ? { utm_source: 'google', utm_medium: 'cpc', utm_campaign: 'services' }
            : {},
      },
      notified_at: index % 6 === 0 ? null : created,
      notify_error: index === 12 ? 'SMTP connect() failed' : null,
    }),
  )
}

for (let index = 0; index < 17; index += 1) {
  const name = NAMES[(index + 5) % NAMES.length]

  submissions.push(
    submission({
      form_id: 2,
      created_at: ago(Math.floor(index / 2), 10 + (index % 8), (index * 7) % 60),
      status_id: [1, 2, 3][index % 3],
      assignee_id: index % 3 === 0 ? null : 2,
      is_read: index > 1,
      values: {
        name,
        phone: `+380 9${index % 9} ${200 + index} 11 ${20 + index}`,
        time: ['morning', 'afternoon', 'evening'][index % 3],
      },
      meta: { ip: '188.163.12.4', page: '/', locale: 'ru' },
      notified_at: ago(Math.floor(index / 2), 10 + (index % 8)),
    }),
  )
}

/** The log of one submission, written on demand: fixtures for every line would be noise. */
export function eventsFor(record: SubmissionRecord): SubmissionEvent[] {
  if (record.events.length > 0) {
    return record.events
  }

  const log: SubmissionEvent[] = [
    {
      id: record.id * 10 + 1,
      type: 'created',
      from: null,
      to: null,
      author: null,
      created_at: record.created_at,
    },
  ]

  if (record.notified_at !== null) {
    log.push({
      id: record.id * 10 + 2,
      type: 'notified',
      from: null,
      to: 'sales@webx-demo.test',
      author: null,
      created_at: record.notified_at,
    })
  }

  if (record.status_id !== 1) {
    log.push({
      id: record.id * 10 + 3,
      type: 'status',
      from: 'Новая',
      to: statuses.find((status) => status.id === record.status_id)?.title.ru ?? null,
      author: { id: 1, name: 'Анна Ковальчук' },
      created_at: record.updated_at,
    })
  }

  if (record.assignee_id !== null) {
    log.push({
      id: record.id * 10 + 4,
      type: 'assignee',
      from: null,
      to: admins.find((admin) => admin.id === record.assignee_id)?.name ?? null,
      author: { id: 1, name: 'Анна Ковальчук' },
      created_at: record.updated_at,
    })
  }

  record.events = log

  return log
}

/** The answers of a submission, with the questions as they were asked at the time. */
export function valuesFor(record: SubmissionRecord): SubmissionValue[] {
  return fields
    .filter((item) => item.form_id === record.form_id)
    .map((item, index) => ({
      id: record.id * 100 + index,
      field_id: item.id,
      name: item.key,
      label: item.title.ru ?? item.key,
      type: item.type,
      value: record.values[item.key] ?? null,
      payload: null,
    }))
}

function transliterate(word: string): string {
  const map: Record<string, string> = {
    а: 'a',
    б: 'b',
    в: 'v',
    г: 'g',
    д: 'd',
    е: 'e',
    ё: 'e',
    ж: 'zh',
    з: 'z',
    и: 'i',
    й: 'i',
    к: 'k',
    л: 'l',
    м: 'm',
    н: 'n',
    о: 'o',
    п: 'p',
    р: 'r',
    с: 's',
    т: 't',
    у: 'u',
    ф: 'f',
    х: 'h',
    ц: 'c',
    ч: 'ch',
    ш: 'sh',
    щ: 'sch',
    ъ: '',
    ы: 'y',
    ь: '',
    э: 'e',
    ю: 'yu',
    я: 'ya',
  }

  return word
    .toLowerCase()
    .split('')
    .map((letter) => map[letter] ?? letter)
    .join('')
}

/** `submissions_count` and `unread_count` are derived: a save must not have to keep them. */
export function countForms(): void {
  for (const form of forms) {
    const mine = submissions.filter((record) => record.form_id === form.id)
    const spam = new Set(statuses.filter((status) => status.is_spam).map((status) => status.id))

    form.submissions_count = mine.length
    form.unread_count = mine.filter(
      (record) => !record.is_read && !spam.has(record.status_id),
    ).length
  }

  for (const status of statuses) {
    status.submissions_count = submissions.filter((record) => record.status_id === status.id).length
  }
}

countForms()
