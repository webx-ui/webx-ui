import { computed, defineComponent, h, useAttrs, type Component } from 'vue'
import { useTranslate } from './i18n'
import { takesShortcodes, useShortcodesWhen } from './shortcodes'

/**
 * `wx-input` and `wx-textarea` on a panel screen: the core's field, with the site's shortcodes
 * behind `[` when the node asks for them (`props.shortcodes: true`) — the `[]` button and the
 * suggestions a block's text field has, for a product's summary or a review's text.
 *
 * The flag is the node's and stops here: the core field never sees it, so it does not land on
 * the input as an attribute. A node that brought `tokens` of its own — a block's form, which
 * hands every text field the shortcodes itself — keeps them. Nothing else is declared, so the
 * node's props travel as attributes and arrive as they were written, `localized` included.
 */
export function shortcodeTextField(control: Component, type: string, name: string): Component {
  return defineComponent({
    name,
    inheritAttrs: false,
    setup() {
      const attrs = useAttrs()
      const t = useTranslate('webx-admin')
      const wanted = () => attrs.tokens === undefined && takesShortcodes(type, attrs.shortcodes)
      const tokens = useShortcodesWhen(wanted)

      const bound = computed(() => {
        const rest: Record<string, unknown> = { ...attrs }
        delete rest.shortcodes

        return wanted() && tokens.value.length > 0
          ? {
              tokensTitle: t('shortcodes.title'),
              tokensLabel: t('shortcodes.insert'),
              ...rest,
              tokens: tokens.value,
            }
          : rest
      })

      return () => h(control, bound.value)
    },
  })
}
