<script setup lang="ts">
import { WxEmpty } from '@webx-ui/core'
import { useTranslate, WxListScreen } from '@webx-ui/module-admin'
import { useOrdersMessages } from './i18n'

/**
 * The list of orders, empty until the service that places them and the list that reads them
 * arrive (spec §11.2): the section stands in the menu from the first release of the package.
 */
useOrdersMessages()
const t = useTranslate('webx-orders')
</script>

<template>
  <WxListScreen :title="t('module.orders')">
    <WxEmpty
      icon="clipboard"
      size="lg"
      :title="t('module.empty')"
      :description="t('module.empty-help')"
    />
  </WxListScreen>
</template>
