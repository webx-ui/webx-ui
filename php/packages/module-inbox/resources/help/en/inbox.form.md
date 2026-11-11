---
title: Form
guide: inbox#the-form-on-the-site
---

# about

Here you set what a form asks, what the visitor sees after sending, who hears about a submission and
how the form gets onto a page. A question on the «Fields» tab saves on its own, in its own window,
and so does the order of the questions. Everything on the other tabs waits for «Save» at the bottom.

# howto

## How to put a new form to work

1. On the «Fields» tab, press «New field» for each question, and drag them into order.
2. On the «Notifications» tab, say who should hear about submissions.
3. On the «General» tab, write what the visitor sees after sending, and press «Save».
4. On the «Embedding» tab, copy the tag and put it on the page, or pass it to whoever looks after the
   site. If a block of the site has a form to choose, choose this one there instead.
5. Send the form yourself and find the submission in the list.

## How to ask a question with ready answers

1. On the «Fields» tab, press «New field».
2. In «Type», choose «Drop-down», «One of several» or «Several of several».
3. Under «Choices», press «Add a choice» for each answer and fill in «Value» and «Label».
4. Press «Save». A field like this cannot be saved without a choice.

## How to show the answers as cards with a picture

1. Open a «One of several» or «Several of several» field.
2. In «Show the choices as», choose «Cards», and set «Cards in a row».
3. Under each choice, write a description and pick a picture from the media library.
4. Press «Save». The answer in a submission stays the label of the choice.

## How to ask a question only for some answers

1. Put a question with ready answers above it: «Drop-down», «One of several», «Several of several»
   or «Consent».
2. Open the question that depends on it, and under «Show» choose «If».
3. Choose the question above, then the answers it is asked for.
4. Press «Save». In the list, the question now says when it is asked.

## How to split a form into steps

1. On the «Fields» tab, press «Step»: a step row goes to the end of the list.
2. Write its title, and drag the row above the first field of the step. The fields under a row, down
   to the next one, are asked on one screen; the fields above the first row are step 1, called by the
   title of the form.
3. To ask a step only for some answers, set «Show» in its window, the way a question has it.
4. Press «Save». On the site the visitor sees one step at a time, with «Back» and «Next»; «Next»
   checks the step before going on. Without JavaScript all the steps stand on one page.

## How to show the visitor what they chose

1. In a form of steps, press «New field» and choose the type «Summary».
2. Write its title — «Your request» — and, under «What to show», the questions to repeat. Empty — every
   question above it but consents, files and hidden fields.
3. Drag it to where the visitor should check the answers: usually the last step, above the contacts.
4. Press «Save». On the site the summary lists the answers with «Edit» beside each, which takes the
   visitor back to the question. Nothing of it goes into the submission. A form on one page, and a page
   without JavaScript, show no summary: every answer is in sight there.

## How to ask for a day and a time

1. Press «New field» and choose the type «Date and time».
2. Set the step of the times, the hours of a day («From» and «To») and the days of the week. The last
   time is a step before «To»: 09:00–17:00 by 30 minutes ends at 16:30. The window shows the times of
   a day as the site will offer them.
3. Press «Save». On the site the visitor picks a day in a calendar and a time from the list. Times are
   in the time zone of the site — «Settings › Contacts» — and the form says which. A day or a time
   already gone cannot be chosen; without JavaScript the browser's date and a list of times stand in.
4. The submission keeps the day and the time with the zone: «12.12.2026, 10:30 (Europe/…)». As a column
   of the list, it sorts by the moment. Two visitors may choose the same time: whoever reads the
   submission settles it with the statuses and the notes.

## How to choose what the list of submissions shows

1. On the «Fields» tab, press «Edit» in the «···» menu of the field.
2. Tick «A column in the list».
3. Press «Save».

While no field is ticked, the first two short answers stand in for the columns. On a narrow screen the
list drops columns from the last one, and the first always stays.

## How to stop asking a question

1. On the «Fields» tab, press «Edit» in the «···» menu of the field.
2. Untick «Shown» and press «Save», and the question can come back later.
3. Or press «Delete» in the same menu if it will not.

Either way the answers already received stay in the submissions.

## How to put the visitor's name in the thank-you

1. On the «Fields» tab, open the field and look at «Name» under «Advanced»: `name`, for example.
2. On the «General» tab, write it into «Heading» or «Text»: «Thanks, `{{field.name}}`!»
3. Press «Save» and send the form to see it.

An empty answer and a file put nothing in. This works for a name of small Latin letters, digits and
underscores. Any other name stays on the page as you wrote it.

## How to protect a form from spam

1. On the «Antispam» tab, leave «Hidden field» on and the numbers above zero.
2. Ask whoever looks after the site which captcha it has keys for.
3. Choose that one in «Captcha» and read the line under the list.
4. If a warning says the site has no keys, set «Captcha» back to «Off» for now.
5. Press «Save» and send the form yourself to check.

## How to check that submissions reach people

1. Look above the tabs: a warning there means nobody is told about this form.
2. If the form tells by letter, read «Letters leave through» on the «Notifications» tab, and the
   warnings under it: a test address means letters reach nobody real.
3. Send the form on the site with answers of your own.
4. Open the submission and look at «Notifications» on its «Details» tab.

The same answers sent again within 15 minutes update the first submission instead of adding a new one,
unless the site is set up otherwise. For a second test, change an answer.

# fields

## Address

Pages and blocks find the form by this address and do not follow a change. After a change they show
nothing in its place, with no error, until the tag or the block is updated.

## After sending

«Text on the button» replaces the word on the button, and left empty it stays «Send». «Heading» and
«Text» appear in place of the form, and with nothing written the visitor still gets a short thank-you.

## Instead of the thank-you, go to

«This site» remembers the page itself, so a new address of the page does not break it, and a page gone
from the site gives the thank-you. A path typed under «An address» gets the visitor's language, and a
full address goes as written.

## Field with the visitor's address

The list offers the «E-mail» fields of this form, so add one first. With «None», a reply to the letter
goes to the address the letters come from, and not to the visitor.

## Who is written to

«Add an administrator» offers those who may open submissions: they are written to at the address of
their account, in the language of their panel. A typed address gets the letter in the language the
submission came in. An administrator deleted or switched off later gets nothing, and the line says so.

## How to tell about a submission

This choice is here only if the site has the «Notifications» section, where channels are set up. A
form tells one way, never both. The settings of the other way stay on the form for when you switch back.

## Fastest a person could send it, in seconds

What arrives faster is refused, and the visitor is told the form could not be sent. Keep the number
low: somebody whose browser fills the form in by itself is quick too.

## Sends a minute from one address

Only submissions that got through count, so somebody correcting a mistake is not stopped. People on
one connection, in one office for example, are one address: raise the number if they all use the form.

## Captcha

Visitors prove they are not robots, so turn it on when robots get past the other checks. The keys are
the site's: without them a warning appears here, and the form refuses every submission until they are
added.

## Show

Only a question with ready answers above this one can be a condition, so drag it higher if it is not
offered. Several answers in one row mean any of them, several rows mean all of them. A visitor does not
see a hidden question, and an answer to it is not kept. A question others depend on cannot be deleted,
dragged below them or lose an answer they count on: change their «Show» first.

## Show the choices as

Cards suit answers that need a few words or a picture to be told apart. Each card is still a real
choice: it is picked with a click anywhere on it or with the keyboard, like a list. The description and
the picture are only how the field looks — the submission keeps the label of the choice.

## Cards in a row

How many cards stand side by side on a wide form. On a narrow screen, and in a narrow column, the cards
stand one under another whatever is set here.

## Picture

A file of the media library, shown above the label of the card. The field appears only when the site
has the media library. If the file is deleted later, the card stays, without a picture.

## Short label in the indicator

The line over the steps names each step by its title. When a title is long, a word or two here takes
its place there; the title stays over the fields of the step.

## Words of the «Next» button

What the button that leaves this step says — «Choose the time» instead of «Next». Empty, the site's own
word is used. The last step has no «Next»: it is sent with the button of the form.

## What to show

The questions the summary repeats, in the order of the form. Empty — every question above it that the
visitor was shown and answered, but consents, files and hidden fields. A question deleted later simply
drops out.

## Step of the times

How far apart the times of the list are: 15, 20, 30, 60, 90 or 120 minutes. With «From» and «To» it
makes the times of a day; a day with no room for one time is refused.

## Days of the week

The days the calendar offers. None chosen — every day. «Weekdays» chooses Monday to Friday. A day not
offered is grey in the calendar, and the site refuses it even from a page without JavaScript.

## Words about the time zone

What the form says under the calendar about the time, instead of the site's own words with the name of
the zone. The zone itself is the site's, one for every form: it is changed in «Settings › Contacts».

## Required

The form cannot be sent without this answer, and a «Consent» field has to be ticked. Careful with a
«Hidden» field: if the page does not fill it in, every submission is refused.

## Name

Changing it does not touch the answers already received. What stops working is whatever calls the
field by its old name: `{{field.name}}` in the thank-you and `:values` in a tag on a page.
