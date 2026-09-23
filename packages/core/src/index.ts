import './styles/index.css'

export * from './components'
export { WebxUI, type WebxUiOptions } from './plugin'
export type { ControlSize, ControlStatus, ValidationErrors } from './composables/useFormField'
export { useControlAttrs, type ControlAttrs } from './composables/useControlAttrs'
export {
  provideLocales,
  useLocales,
  useLocalized,
  localeLabel,
  localizedValue,
  localesKey,
  type LocaleOption,
  type LocalesContext,
  type LocalizedValue,
  type LocalizedFieldProps,
} from './composables/useLocalized'
export {
  provideDateLocale,
  useDateLocale,
  dateLocaleKey,
  type DateLocaleSource,
} from './composables/useDateLocale'
export type { DateFnsLocale } from './internal/dateLocale'
export { useElementWidth } from './composables/useElementWidth'
export { useHoverPointer } from './composables/useHoverPointer'
export {
  useToast,
  toast,
  toastQueue,
  dismissToast,
  removeToast,
  clearToasts,
  type ToastApi,
  type ToastHandle,
  type ToastOptions,
  type ToastRecord,
  type ToastType,
  type ToastAction,
} from './composables/useToast'
export {
  openModal,
  createModal,
  useModal,
  connectModals,
  modalKey,
  type ModalHandle,
  type ModalOptions,
  type ModalPromise,
} from './composables/useModal'
export { confirm, type ConfirmOptions } from './composables/confirm'
export { openImageEditor } from './composables/imageEditor'
export {
  useResponsiveShell,
  shellLayoutFor,
  type ResponsiveShell,
  type ResponsiveShellOptions,
  type ShellLayout,
} from './composables/useResponsiveShell'
