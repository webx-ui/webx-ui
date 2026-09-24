// The screen renderer's own styles — a column's stack, the placeholder of an unknown type —
// ride with the panel's: every site imports this package's stylesheet and none imports the
// schema's, so a rule kept only there reached the playground (which reads the sources) and no
// site at all.
import '@webx-ui/schema/style.css'

export { createAdmin, type Admin, type AdminPlugin, type CreateAdminOptions } from './createAdmin'
export {
  createAdminContext,
  provideAdmin,
  useAdmin,
  adminKey,
  type AdminContext,
  type AdminState,
} from './admin'
export {
  createI18n,
  provideI18n,
  useI18n,
  useTranslate,
  i18nKey,
  type Dictionary,
  type I18n,
  type I18nState,
  type LocaleDescriptor,
  type Messages,
  type Translate,
} from './i18n'
export {
  createTheme,
  provideTheme,
  useTheme,
  themeKey,
  type CreateThemeOptions,
  type Theme,
  type ThemeController,
  type ThemePreference,
  type ThemeState,
} from './theme'
export { adminMessages } from './messages'
export { renderMarkdown } from './markdown'
export { createDates, useDates, type DateLike, type Dates } from './dates'
export { errorText, useErrorText } from './errors'
export { createNotesApi, type EntityNote, type NoteAuthor, type NotesApi } from './notes'
export {
  createLinksApi,
  emptyLink,
  type LinkCandidate,
  type LinkRel,
  type LinkSourceInfo,
  type LinkTarget,
  type LinkValue,
  type LinksApi,
  type ResolvedLink,
} from './links'
export {
  createHttp,
  readCookie,
  HttpError,
  type Http,
  type HttpOptions,
  type RequestOptions,
} from './http'
export { adminTypes } from './screenTypes'
export {
  categoryRoutes,
  createCategoriesApi,
  itemOrderMode,
  reorderItems,
  useCategoryEditor,
  useCategoryWords,
  useItemOrder,
  type CategoriesApi,
  type CategoriesOptions,
  type CategoriesPayload,
  type CategoryDetail,
  type CategoryEditorContext,
  type CategoryRow,
  type CategoryWord,
  type ItemOrder,
  type ItemOrderMode,
  type ItemOrderState,
} from './categories'
export {
  COLLECTION_MAX_LIMIT,
  collectionSources,
  defaultMarkup,
  normaliseCollection,
  type CollectionRelated,
  type CollectionRelationTarget,
  type CollectionSourceInfo,
  type CollectionValue,
} from './collections/api'
export {
  createRelationsApi,
  normaliseRelations,
  provideRelationOwner,
  relationOwnerKey,
  useRelationOwner,
  type RelationCandidate,
  type RelationOwner,
  type RelationsApi,
} from './relations/api'
export type {
  AdminModule,
  AdminStatus,
  AdminUser,
  Manifest,
  ManifestModule,
  NavEntry,
  PickedImage,
  RowAction,
  ScreenAction,
  AppliedFilter,
} from './types'

export { default as AdminShell } from './AdminShell.vue'
export { default as AdminNav } from './AdminNav.vue'
export { default as AdminLanding } from './AdminLanding.vue'
export { default as WxScreen } from './Screen.vue'
export { default as WxListScreen } from './ListScreen.vue'
export { default as WxScreenHead } from './ScreenHead.vue'
export { rowMenuWidth } from './rowMenu'
export { default as WxRowMenu } from './RowMenu.vue'
export { default as WxSaveState } from './SaveState.vue'
export { default as WxFilterChips } from './FilterChips.vue'
export { default as WxBackButton } from './BackButton.vue'
export { default as WxRenameButton } from './RenameButton.vue'
export { default as WxHelpButton } from './HelpButton.vue'
export { default as WxDate } from './DateText.vue'
export { default as WxNotes } from './NotesFeed.vue'
export { default as WxBackupNote } from './BackupNote.vue'
export { default as WxRichTextField } from './RichTextField.vue'
export { default as WxLinkPicker } from './LinkPicker.vue'
export { default as WxLinkField } from './LinkField.vue'
export { default as WxCategoriesPage } from './categories/CategoriesPage.vue'
export { default as WxCategoryCreateDialog } from './categories/CategoryCreateDialog.vue'
export { default as WxCategoryEditorPage } from './categories/CategoryEditorPage.vue'
export { default as WxCategoriesField } from './categories/CategoriesField.vue'
export { default as WxCollectionField } from './collections/CollectionField.vue'
export { default as WxRelationsField } from './relations/RelationsField.vue'
export { default as WxSlugField } from './SlugField.vue'
/** The shared address field under the name it was released with. */
export { default as WxCategorySlug } from './SlugField.vue'
export {
  provideRecordAddress,
  recordAddressKey,
  useRecordAddress,
  type RecordAddress,
} from './address'
