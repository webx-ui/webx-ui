import './styles/index.css'

export * from './components'
export { WebxUI, type WebxUiOptions } from './plugin'
export type { ControlSize, ControlStatus, ValidationErrors } from './composables/useFormField'
export { useControlAttrs, type ControlAttrs } from './composables/useControlAttrs'
export { useElementWidth } from './composables/useElementWidth'
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
