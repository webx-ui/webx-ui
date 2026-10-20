/**
 * The default templates `webx-ui/module-inbox` declares for `inbox.submission`
 * (`SubmissionTemplates`), as `GET meta` gives them on a site in Russian and English — copied
 * here so that the panel of the playground shows the real letter and message.
 */
export const SUBMISSION_SAMPLES = {
  email: {
    subject: {
      ru: 'Новая заявка: {{form}}',
      en: 'New submission: {{form}}',
    },
    template: {
      ru: '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">\n  <tr>\n    <td style="padding:0 0 16px 0;">\n      <h1 style="margin:0 0 4px 0;font-size:20px;line-height:1.3;font-weight:600;">{{form}}</h1>\n      <p style="margin:0;color:#6b7280;font-size:13px;">Получено {{created_at}}</p>\n    </td>\n  </tr>\n  <tr>\n    <td>\n      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">\n        {{#answers}}\n        <tr>\n          <td style="padding:8px 12px 8px 0;vertical-align:top;width:38%;color:#6b7280;border-bottom:1px solid #e5e7eb;">{{label}}</td>\n          <td style="padding:8px 0;vertical-align:top;border-bottom:1px solid #e5e7eb;">{{value}}</td>\n        </tr>\n        {{/answers}}\n        {{^answers}}\n        <tr>\n          <td style="padding:8px 0;color:#6b7280;">В этой заявке нет значений.</td>\n        </tr>\n        {{/answers}}\n      </table>\n    </td>\n  </tr>\n  {{#files}}\n  <tr>\n    <td style="padding:16px 0 0 0;">\n      <p style="margin:0 0 4px 0;font-weight:600;">Вложения</p>\n      <p style="margin:0;">{{files}}</p>\n    </td>\n  </tr>\n  {{/files}}\n  <tr>\n    <td style="padding:24px 0;">\n      <a href="{{submission_url}}" style="display:inline-block;padding:10px 18px;border-radius:6px;background-color:#111827;color:#ffffff;text-decoration:none;font-weight:500;">Открыть в панели</a>\n    </td>\n  </tr>\n  {{#page}}\n  <tr>\n    <td style="padding:0 0 8px 0;font-size:13px;color:#6b7280;">\n      Страница: {{page}}\n    </td>\n  </tr>\n  {{/page}}\n  {{#utm}}\n  <tr>\n    <td style="padding:0 0 8px 0;font-size:13px;color:#6b7280;">UTM: {{utm}}</td>\n  </tr>\n  {{/utm}}\n  {{#reply_to}}\n  <tr>\n    <td style="padding:8px 0 0 0;font-size:13px;color:#6b7280;">Ответ на это письмо уйдёт посетителю: {{reply_to}}.</td>\n  </tr>\n  {{/reply_to}}\n</table>\n',
      en: '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">\n  <tr>\n    <td style="padding:0 0 16px 0;">\n      <h1 style="margin:0 0 4px 0;font-size:20px;line-height:1.3;font-weight:600;">{{form}}</h1>\n      <p style="margin:0;color:#6b7280;font-size:13px;">Received {{created_at}}</p>\n    </td>\n  </tr>\n  <tr>\n    <td>\n      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">\n        {{#answers}}\n        <tr>\n          <td style="padding:8px 12px 8px 0;vertical-align:top;width:38%;color:#6b7280;border-bottom:1px solid #e5e7eb;">{{label}}</td>\n          <td style="padding:8px 0;vertical-align:top;border-bottom:1px solid #e5e7eb;">{{value}}</td>\n        </tr>\n        {{/answers}}\n        {{^answers}}\n        <tr>\n          <td style="padding:8px 0;color:#6b7280;">This submission has no values.</td>\n        </tr>\n        {{/answers}}\n      </table>\n    </td>\n  </tr>\n  {{#files}}\n  <tr>\n    <td style="padding:16px 0 0 0;">\n      <p style="margin:0 0 4px 0;font-weight:600;">Attachments</p>\n      <p style="margin:0;">{{files}}</p>\n    </td>\n  </tr>\n  {{/files}}\n  <tr>\n    <td style="padding:24px 0;">\n      <a href="{{submission_url}}" style="display:inline-block;padding:10px 18px;border-radius:6px;background-color:#111827;color:#ffffff;text-decoration:none;font-weight:500;">Open in the panel</a>\n    </td>\n  </tr>\n  {{#page}}\n  <tr>\n    <td style="padding:0 0 8px 0;font-size:13px;color:#6b7280;">\n      Page: {{page}}\n    </td>\n  </tr>\n  {{/page}}\n  {{#utm}}\n  <tr>\n    <td style="padding:0 0 8px 0;font-size:13px;color:#6b7280;">UTM: {{utm}}</td>\n  </tr>\n  {{/utm}}\n  {{#reply_to}}\n  <tr>\n    <td style="padding:8px 0 0 0;font-size:13px;color:#6b7280;">Reply to this letter to answer the visitor: {{reply_to}}.</td>\n  </tr>\n  {{/reply_to}}\n</table>\n',
    },
  },
  telegram: {
    subject: null,
    template: {
      ru: '<b>{{form}}</b>\n{{#answers}}\n<b>{{label}}:</b> {{value}}\n{{/answers}}\n{{#files}}\n\nВложения: {{files}}\n{{/files}}\n{{#page}}\nСтраница: {{page}}\n{{/page}}\n\n<a href="{{submission_url}}">Открыть в панели</a>\n',
      en: '<b>{{form}}</b>\n{{#answers}}\n<b>{{label}}:</b> {{value}}\n{{/answers}}\n{{#files}}\n\nAttachments: {{files}}\n{{/files}}\n{{#page}}\nPage: {{page}}\n{{/page}}\n\n<a href="{{submission_url}}">Open in the panel</a>\n',
    },
  },
  '*': {
    subject: {
      ru: 'Новая заявка: {{form}}',
      en: 'New submission: {{form}}',
    },
    template: {
      ru: '{{form}}\n\n{{fields}}\n\nОткрыть в панели: {{submission_url}}\n',
      en: '{{form}}\n\n{{fields}}\n\nOpen in the panel: {{submission_url}}\n',
    },
  },
}
