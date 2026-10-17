import { defineAsyncComponent } from 'vue'
export { team, type TeamOptions } from './module'
export { createTeamApi, TEAM_API, type TeamApi } from './api'
export { teamMessages } from './messages'
export const WxTeamPage = defineAsyncComponent(() => import('./TeamPage.vue'))
export { default as WxMemberPane } from './MemberPane.vue'
export type {
  MemberDetail,
  MemberQuery,
  MemberRow,
  MembersList,
  MemberSummary,
  SocialLink,
} from './types'
