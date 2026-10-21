# CMS sign-up block

When `j87nl/laravel-cms` is installed, the package registers a block type in the CMS block
registry. Nothing needs to be configured.

| Key              | Class                           | View                     |
| ---------------- | ------------------------------- | ------------------------ |
| `mailing_signup` | `J87NL\Mailing\Cms\SignupBlock` | `mailing::blocks.signup` |

## Fields

| Field          | Rules                                    | Description                                    |
| -------------- | ---------------------------------------- | ---------------------------------------------- |
| `list`         | required, the key of a public list       | The list to sign up for                        |
| `title`        | nullable, up to 160 characters           | A heading above the form                       |
| `intro`        | nullable, up to 1000 characters          | A paragraph above the form                     |
| `button_label` | nullable, up to 80 characters            | The button text; empty uses "Sign up"          |

The list select only offers public lists.

## Rendering

The block renders the same form partial as the [sign-up page](signup.md), with the path of the CMS
page as `return_to`. After a successful sign-up the visitor returns to that page, scrolled to the
form, which then shows the "check your inbox" message.

When the list no longer exists or is no longer public, the block renders nothing.

`toPlainText()` returns the title and intro of the block.

## Form token age

The form token holds the time the form was rendered. It is accepted for up to
`mailing.signup.max_seconds` (48 hours). A page served from a cache longer than that carries an
older token: the visitor then gets "This form has expired. Please try again." and a fresh form on
the sign-up page.
