{{--
    The test provider (Social\DevProvider, APP_ENV=local only): who to come back as. Posts to the
    ordinary callback with the module's `state`, so everything after it is the real flow. Plain
    English: no site shows this page to a visitor.
--}}
<x-webx-users::page title="Test sign-in">
    <p class="webx-account-auth__note">Pretend to be a person a social network sends back. Leave the address empty to see «Almost done».</p>
    <form method="post" action="{{ $action }}" id="webx-users-dev-provider" class="webx-account-auth__form">
        <input type="hidden" name="state" value="{{ $state }}">
        <p><label>E-mail<br><input type="email" name="email" autocomplete="off"></label></p>
        <p><label>First name<br><input type="text" name="first_name" autocomplete="off"></label></p>
        <p><label>Last name<br><input type="text" name="last_name" autocomplete="off"></label></p>
        <p><label>Id at the provider (empty — made from the address)<br><input type="text" name="id" autocomplete="off"></label></p>
        <p><label><input type="checkbox" name="verified" value="1" checked> The provider says the address is verified</label></p>
        <p><button type="submit">Come back to the site</button></p>
    </form>
</x-webx-users::page>
