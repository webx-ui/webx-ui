// Steps 11 and 12 of the LiqPay scenario on the lab (WEBX_UI_PAYMENT_LIQPAY.md §18): notifications
// never reach the lab, so this builds one after notify/success.json for an attempt of the lab,
// signs it with the sandbox key of .lab-env and posts it to the address of the method.
//
//   node scripts/lab-liqpay-notify.mjs <order number>                  twice: same body, same event
//   node scripts/lab-liqpay-notify.mjs <order number> --bad-signature  once, with a spoiled signature
//
// Prints what the site decided and nothing of the keys, the body or the signature: the journal of
// the panel shows those.

import { execFileSync } from 'node:child_process'
import { createHash } from 'node:crypto'
import { readFileSync } from 'node:fs'
import { homedir } from 'node:os'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const SITE = process.env.LAB_SITE ?? join(homedir(), 'work/webx-lab')
const FIXTURE = join(
  dirname(fileURLToPath(import.meta.url)),
  '../php/packages/payment-liqpay/tests/Fixtures/notify/success.json',
)

function fail(message) {
  console.error(message)
  process.exit(1)
}

/** A line `NAME=value` of a dotenv file; the value never leaves this process. */
function envLine(file, name) {
  const line = readFileSync(join(SITE, file), 'utf8')
    .split('\n')
    .find((candidate) => candidate.startsWith(`${name}=`))

  return line
    ?.slice(name.length + 1)
    .trim()
    .replace(/^"(.*)"$/, '$1')
}

/** Runs PHP in the site and reads the JSON it printed last. */
function ask(code) {
  const output = execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], {
    cwd: SITE,
    encoding: 'utf8',
  })
  const line = output
    .split('\n')
    .reverse()
    .find((candidate) => candidate.startsWith('{'))

  return line === undefined ? null : JSON.parse(line)
}

function attemptOf(number) {
  return ask(`
    $order = DB::table('orders')->where('number', '${number}')->first();
    $attempt = $order === null ? null : DB::table('payments')->where('order_id', $order->id)
        ->where('method_key', 'like', 'liqpay%')->orderByDesc('id')->first();
    if ($attempt !== null) {
        echo json_encode([
            'id' => $attempt->id, 'uuid' => $attempt->uuid, 'method' => $attempt->method_key,
            'amount' => $attempt->amount, 'currency' => $attempt->currency,
            'status' => $attempt->status, 'ref' => $attempt->gateway_ref,
        ]), PHP_EOL;
    }
  `)
}

function lastNotification(attempt) {
  return ask(`
    $event = DB::table('payment_gateway_events')->where('payment_id', ${attempt})
        ->where('direction', 'in')->orderByDesc('id')->first();
    $status = DB::table('payments')->where('id', ${attempt})->value('status');
    echo json_encode([
        'outcome' => $event?->outcome, 'signature' => $event?->signature, 'message' => $event?->message,
        'before' => $event?->status_before, 'after' => $event?->status_after, 'status' => $status,
    ]), PHP_EOL;
  `)
}

function bodyFor(attempt, publicKey, privateKey, spoiled) {
  const fixture = JSON.parse(readFileSync(FIXTURE, 'utf8'))
  const now = Date.now()
  const data = Buffer.from(
    JSON.stringify({
      ...fixture.fields,
      public_key: publicKey,
      order_id: attempt.uuid,
      liqpay_order_id: `EMULATED${attempt.id}`,
      payment_id: Number(attempt.ref) || fixture.fields.payment_id,
      amount: attempt.amount / 100,
      amount_debit: attempt.amount / 100,
      amount_credit: attempt.amount / 100,
      currency: attempt.currency,
      currency_debit: attempt.currency,
      currency_credit: attempt.currency,
      create_date: now,
      end_date: now,
    }),
  ).toString('base64')
  const signature = createHash('sha3-256')
    .update(privateKey + data + privateKey)
    .digest('base64')

  return new URLSearchParams({
    data,
    signature: spoiled ? `x${signature.slice(1)}` : signature,
  }).toString()
}

async function send(url, body, attempt, label) {
  const response = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body,
  })
  const seen = lastNotification(attempt.id)

  console.log(
    `${label}: HTTP ${response.status}, outcome ${seen.outcome}, signature ${seen.signature}, ` +
      `${seen.before ?? '—'} → ${seen.after ?? '—'}, attempt is ${seen.status}` +
      (seen.message ? ` — ${seen.message}` : ''),
  )
}

const args = process.argv.slice(2)
const spoiled = args.includes('--bad-signature')
const number = args.find((arg) => !arg.startsWith('--'))

if (number === undefined || !/^[0-9A-Za-z-]+$/.test(number)) {
  fail('Usage: node scripts/lab-liqpay-notify.mjs <order number> [--bad-signature]')
}

const publicKey = envLine('.lab-env', 'LIQPAY_PUBLIC')
const privateKey = envLine('.lab-env', 'LIQPAY_SECRET')

if (!publicKey || !privateKey) {
  fail(`LIQPAY_PUBLIC and LIQPAY_SECRET are not in ${SITE}/.lab-env`)
}

const attempt = attemptOf(number)

if (attempt === null) {
  fail(`Order ${number} has no LiqPay attempt on the lab`)
}

const url = `${envLine('.env', 'APP_URL')}/payments/notify/${attempt.method}`
const body = bodyFor(attempt, publicKey, privateKey, spoiled)

console.log(`Order ${number}, attempt #${attempt.id} (${attempt.status}) → ${url}`)

if (spoiled) {
  await send(url, body, attempt, 'spoiled signature')
} else {
  await send(url, body, attempt, 'first')
  await send(url, body, attempt, 'second')
}
