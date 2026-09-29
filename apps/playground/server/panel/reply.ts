/**
 * An answer whose status is not the router's guess (201 for a POST, 200 otherwise): a 202 for
 * work put in a queue, a 200 for a POST that changed something rather than made it.
 */
export class Reply {
  constructor(
    readonly status: number,
    readonly payload: unknown,
  ) {}
}
