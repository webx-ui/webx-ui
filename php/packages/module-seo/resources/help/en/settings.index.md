# fields

## default-og

Networks and messengers show this picture when somebody shares a page that has none of its own;
visitors of the site never see it. Choose a landscape picture of at least 1200 by 630 pixels. An SVG
will not do: networks cannot show one, and the link goes out without a picture.

## title-template

Changes the title of every page at once: in the browser tab, in search results and in shared links.
Left empty, or without `{title}`, it adds nothing and the title goes out as written, whatever the
grey example says. `{site}` takes «Project name» from the «General» tab, and the counter on a page's
SEO card does not count what the template adds, so keep it short.

## home-crumb

Visitors read this word as the first link of the breadcrumbs, wherever the site shows them, and
search engines get it too. Change it when the shop has a better word for its front page. It is
written for each language of the site.

## robots-txt

The most dangerous field of this tab: the two lines `User-agent: *` and `Disallow: /` close the
whole site to search engines, and its pages drop out of search. Nothing checks what you write, and
neither the sitemap nor «Check an address» in the SEO section looks at this field, so after saving
open `/robots.txt` on the site and read it. Left empty, that address answers «not found» and search
engines go everywhere; if it shows text you did not write, the site has a file of its own, and this
field has no effect.

## org-name

Visitors do not see it on the pages: it tells search engines whose site this is, and they may show
it in the results. Left empty, «Project name» from the «General» tab stands in. It is written for
each language of the site.

## org-logo

This logo goes to search engines only, and they may show it beside the organization in the results.
The logo in the header of the site does not change.

## org-socials

Visitors do not see this list: it tells search engines that these profiles belong to the same
organization. A profile on x.com or twitter.com also names the site's account in links shared there.
The networks on the «Contacts» tab are added by themselves, so there is no need to repeat them.

## org-social-url

Copy the full address of the profile from the browser, with its `https://`. A line that is not a
full address is left out, and search engines do not get it.

## normalise-host

Open the site under the name you are about to choose first, and make sure it works. The panel
follows this setting too: if that name does not lead to the site, every page and the panel itself
stop opening, and only whoever looks after the server can bring them back. Whoever works in the
panel under the other name may have to sign in again.

## normalise-https

Turn it on only when the site already opens at `https://` without a browser warning: visitors see
nothing else change. If https does not work, or the server is not set up for it, every page and the
panel itself end in an endless redirect, and you cannot get back in to turn this off. Ask whoever
looks after the server first, and remember that browsers remember this redirect for a while after
it is gone.

## normalise-slashes

Works on every address of the site. A double slash is usually a mistake in somebody's link, so the
visitor simply lands on the right page. Turned off again, such an address opens the page as it is,
and search engines may count it as a second one.

## normalise-index

Works on every address of the site, and `/index.php/about` goes to `/about` too. It is meant for a
site that moved from an older engine and has links ending in `index.php` or `index.html`: a visitor
lands on the section itself. The addresses of the panel stay as they are.

## normalise-trailing

Works on every page of the site at once: `/about/` goes to `/about`. The site writes its own links
without the slash, so a visitor notices nothing, and search results with the slash change over the
following weeks. Set back to «Leave as it is», both forms open the same page, and search engines may
count them as two.

## normalise-case

Works on every page of the site at once, and goes first: a redirect or a rule written with capitals,
such as `/About`, stops working, so write those in lower case before turning this on. Turned off
again, both spellings open the same page, and search engines may count them as two.

## links-heading

This field is there only on a site with interlinking. Visitors read it above the block of links on a
page, and left empty the block says «See also» in the language of the page. It is written for each
language of the site.
