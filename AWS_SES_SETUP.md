# AWS SES runbook — Old Ways, One A Day

Status at the time of writing: **domain verified? no. DKIM? no. SPF/DMARC? yes.** The site stores
every signup already, so nobody is lost while this is set up.

Architecture (deliberate): the **site captures, Hermes sends**. The SES credential never touches
shared hosting, and delivery logic lives in one place we control.

```
subscribe.php  ──appends──►  data/subscribers.csv  ──picked up by──►  send_starter_ses.py  ──►  SES
   (site, validates + honeypot)        (on the host)                    (Hermes, local)          (SPF+DKIM)
```

## 1. Verify the domain in SES

AWS console → **SES** → *Configuration → Verified identities* → **Create identity** → **Domain** →
`theoldways.online` → **Easy DKIM** (RSA 2048).

It returns **three CNAME records** like

```
<token1>._domainkey.theoldways.online  →  <token1>.dkim.amazonses.com
<token2>._domainkey.theoldways.online  →  <token2>.dkim.amazonses.com
<token3>._domainkey.theoldways.online  →  <token3>.dkim.amazonses.com
```

Add them in **hPanel → Domains → theoldways.online → DNS Zone Editor**, exactly as given. Verification
usually takes minutes, sometimes a few hours.

## 2. What is already in DNS

| Record | Value | State |
|---|---|---|
| `TXT @` | `v=spf1 include:amazonses.com ~all` | ✅ present |
| `TXT _dmarc` | `v=DMARC1; p=none; rua=mailto:dmarc@theoldways.online` | ✅ present |
| `MX` | none | expected — no mailbox on the domain yet |

**If a Hostinger mailbox is added later so replies to the starter land somewhere**, the SPF line must
be *merged*, not replaced — do not overwrite it with Hostinger's value and lose `amazonses.com`.

Optional but recommended for DMARC alignment: set a **custom MAIL FROM domain** in SES (e.g.
`mail.theoldways.online`) and add the MX + TXT records it returns.

## 3. Get credentials

IAM → *Users* → create `oldways-ses` → **Access key** (programmatic) with this inline policy:

```json
{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Action": ["ses:SendEmail", "ses:SendRawEmail", "ses:GetAccount", "ses:ListEmailIdentities"],
    "Resource": "*"
  }]
}
```

Save to `C:\Users\tladi\AppData\Local\hermes\secrets\aws_ses.json` (never paste it into chat):

```json
{
  "access_key_id": "AKIA…",
  "secret_access_key": "…",
  "region": "ap-southeast-1",
  "from_address": "hello@theoldways.online",
  "from_name": "Old Ways, One A Day"
}
```

`ap-southeast-1` (Singapore) is the closest SES region and supports everything we need.

## 4. Leave the sandbox

SES → *Account dashboard* → **Request production access**. Until AWS approves, SES will only deliver
to verified addresses — so test sends to your own inbox work immediately, real signups do not.

State it as what it is: transactional mail to people who asked for it, one message per signup, with
bounces handled. Approval is usually inside 24 hours.

## 5. Run it

```bash
cd C:/Users/tladi/seven-day-challenge
"C:/Users/tladi/.hermes/darwin-venv/Scripts/python.exe" products/send_starter_ses.py --check
"C:/Users/tladi/.hermes/darwin-venv/Scripts/python.exe" products/send_starter_ses.py --dry-run
"C:/Users/tladi/.hermes/darwin-venv/Scripts/python.exe" products/send_starter_ses.py
```

- `--check` prints the verified identities, whether sending is enabled, and how many signups wait.
- `--dry-run` lists recipients and touches nothing.
- The real run attaches `site/starter.pdf` and writes `site/data/starter_sent.jsonl`, so a re-run
  never double-sends. Safe to cron once a day.

## 6. After the first successful send

1. Turn **off** the hosting-side sender in `subscribe.php` (`$HOST_SENDS_STARTER = false`) so there
   is exactly one sender and nobody gets two copies of the starter.
2. Add a daily cron for the sender (`hermes cron` script-only, no agent tokens).
3. Consider a `hello@theoldways.online` mailbox for replies, then merge the SPF line as above.

## Cautions

- **Reputation is ours now.** Only ever mail people who asked. No bought lists, no re-mailing
  non-openers with a different subject.
- Watch the SES bounce and complaint rates in the console; a list with no bounce handling gets
  throttled fast.
- The region in `aws_ses.json` must match where the identity was verified or every send returns
  `MessageRejected: Email address is not verified`.
