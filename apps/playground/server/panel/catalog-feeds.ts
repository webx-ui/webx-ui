/*
 * The catalogue's feeds (WEBX_UI_CATALOG_FEEDS.md §11) in memory: the list, with nothing in it
 * until the panel can make a feed.
 */
type On = (method: string, pattern: string, handler: () => unknown) => void

export function registerFeeds(on: On): void {
  on('GET', '/catalog/feeds', () => ({
    data: [],
    current_page: 1,
    last_page: 1,
    per_page: 50,
    total: 0,
  }))
}
