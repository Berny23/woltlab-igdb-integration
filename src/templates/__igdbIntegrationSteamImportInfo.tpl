{if $steamRequestFailed}
	<p class="error">{lang}wcf.igdb_integration.dialog.steam_import_request_failed{/lang}</p>
{elseif !$steamGameCount}
	<p class="error">{lang}wcf.igdb_integration.dialog.steam_import_empty{/lang}</p>
{else}
	<p>{lang}wcf.igdb_integration.dialog.steam_import_confirm{/lang}</p>
{/if}
