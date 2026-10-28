{{--
    `<x-webx-users::sign-in-dialog />` — the dialog of the sign-in (spec §12.1) where a template
    wants it. Printed once a page: a page with an opener and no dialog gets it before </body> by
    itself, so a checkout only needs the button (`<x-webx-users::sign-in-button />`).
--}}
{!! app(\WebxUi\Users\SignInDialog::class)->render() !!}
