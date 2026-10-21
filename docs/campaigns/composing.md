# Composing campaigns

A campaign is one mailing to one list. Campaigns are managed at `/admin/mailing/campaigns` and need
the `mailing.campaigns.edit` permission. Scheduling and test sends also need
`mailing.campaigns.send`.

## Fields

| Field        | Rules                               | Description                                                     |
| ------------ | ----------------------------------- | --------------------------------------------------------------- |
| `list_id`    | required                            | The list to send to                                             |
| `subject`    | required, up to 150 characters      | The subject line                                                |
| `preheader`  | nullable, up to 150 characters      | The short text mail clients show next to the subject            |
| `body_html`  | required, up to 200000 characters   | The content, written in the rich text editor                    |
| `body_text`  | nullable, up to 100000 characters   | The plain-text part                                             |

The body editor is `mailing.rich_text.editor` (default `hugerte`) with the options from the config
key in `mailing.rich_text.options_config_key`.

Only drafts can be edited or deleted.

## Sanitizing

The HTML body is cleaned by the `HtmlSanitizer` contract when the campaign is saved, and again when
a mail is rendered. The default `BasicHtmlSanitizer` (Symfony HtmlSanitizer) keeps only:

- `p`, `br`, `h2`, `h3`, `h4`, `strong`, `b`, `em`, `i`, `blockquote`, `ul`, `ol`, `li`, `table`,
  `thead`, `tbody`, `tr`
- `a` with `href` and `title`
- `th` with `colspan`, `rowspan` and `scope`, and `td` with `colspan` and `rowspan`

Links must use `http`, `https`, `mailto` or `tel`, and relative links are removed. Images and
inline styles are removed. The [link check](preflight.md#links) only accepts `https` links.

To allow more, bind your own sanitizer, see [Contracts](../contracts.md#htmlsanitizer).

## The plain-text part

When `body_text` is left empty, it is generated from the HTML on save: links become
`label (url)`, list items start with `- `, and paragraphs are separated by a blank line.

When you edit the HTML later and the text part is still the unchanged generated text, it is
generated again. Once you change the text part yourself, it is kept.

## The mail layout

Campaign mails use `mailing::mail.campaign` (HTML) and `mailing::mail.campaign-text` (text):

- A single table-based layout, at most 600 pixels wide.
- The list name above the content.
- The preheader as a hidden text at the start of the body.
- The content, with inline styles added to `p`, `h2` to `h4`, `a`, `ul`, `ol`, `li`, `blockquote`,
  `table`, `th` and `td` (`J87NL\Mailing\Html\EmailStyler`). An element that already has a `style`
  attribute is left alone.
- A footer with the sender, why the recipient gets the mail, and the unsubscribe link.

The From address and Reply-To are those of the list. The mail carries the
[list headers](../public/unsubscribe.md#list-headers).

Publish the views with `--tag=mailing-views` to change the layout.

## The campaign page

The campaign page shows the status, the list, the number of recipients, the transport, the
schedule and progress, and the counts per [send status](sending.md#send-statuses).

While the campaign is a draft it also shows:

- the [pre-flight checks](preflight.md),
- the test form,
- the schedule form, or the reason scheduling is blocked.

Once the campaign has started, it shows the send records with their status and last response.

## Test sends

A test send goes to up to five addresses, separated by commas, semicolons or spaces. The campaign
page only shows the test form for drafts.

- Suppressed addresses are skipped. When all are suppressed, nothing is sent.
- Each admin can send at most ten test mails per hour.
- The mail is sent through the list mailer, at once, not through the queue.

A test send is the real message: the same layout, the list headers, the footer with an unsubscribe
link and the driver marking, such as the VERP Return-Path of the Postfix driver. The unsubscribe
link uses the placeholder token `preview`, which does not unsubscribe anyone.

## Scheduling

The schedule form has one optional date and time, entered in `mailing.timezone`. Empty means now.
A time in the past is treated as now.

Scheduling is refused when:

- a pre-flight check is red (the checks run again at that moment),
- the list has no confirmed subscribers.

A scheduled campaign is started by the next run of `mailing:dispatch` after its time. Until then it
can be unscheduled, which makes it a draft again. See [Sending](sending.md).

## Campaign statuses

`J87NL\Mailing\Enums\CampaignStatus`:

| Status      | Meaning                                       | Actions                              |
| ----------- | --------------------------------------------- | ------------------------------------ |
| `draft`     | Being written                                 | Edit, delete, test, schedule         |
| `scheduled` | Waiting for its time                          | Unschedule, cancel                   |
| `sending`   | Sends are being released                      | Pause, cancel, retry failed sends    |
| `paused`    | No new sends are released                     | Resume, cancel, retry failed sends   |
| `sent`      | Every send is finished                        | Retry failed sends                   |
| `cancelled` | Stopped; queued and deferred sends are cancelled |                                   |

`J87NL\Mailing\Actions\ChangeCampaignStatus` holds the transitions: `schedule()`, `unschedule()`,
`pause()`, `resume()`, `cancel()` and `retryFailed()`. Each transition only applies when the campaign
is in an allowed status at that moment, so two admins clicking at once cannot both succeed.

`cancel()` also accepts a `draft` when called from code. The admin does not offer cancel for
drafts; it offers delete instead.
