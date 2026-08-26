{include file='header' pageTitle='wcf.igdb_integration.page.game_list_title'}

<header class="contentHeader">
	<div class="contentHeaderTitle">
		<h1 class="contentTitle">{lang}wcf.igdb_integration.page.game_list_title{/lang}</h1>
		<p class="contentHeaderDescription">{lang}wcf.igdb_integration.page.acp_game_list_subtitle{/lang}</p>
	</div>

	<nav class="contentHeaderNavigation">
		<ul>
			{if $canRefreshGames}
				<li><button type="button" class="button" id="igdbIntegrationRefreshAllButton">{icon size=16 name='rotate' type='solid'}
						<span>{lang}wcf.igdb_integration.game.refresh_all{/lang}</span></button></li>
			{/if}

			{event name='contentHeaderNavigation'}
		</ul>
	</nav>
</header>

<div class="section">
	{unsafe:$gridView->render()}
</div>

<script data-relocate="true">
	require(['Language', 'WoltLabSuite/Core/Controller/IgdbIntegrationGameRefresh'], (Language, { setupRefreshAllButton }) => {
		Language.addObject({
			'wcf.igdb_integration.game.refresh.missing_title': '{jslang}wcf.igdb_integration.game.refresh.missing_title{/jslang}',
			'wcf.igdb_integration.game.refresh.missing_message': '{jslang}wcf.igdb_integration.game.refresh.missing_message{/jslang}',
			'wcf.igdb_integration.game.refresh_all': '{jslang}wcf.igdb_integration.game.refresh_all{/jslang}',
			'wcf.igdb_integration.game.refresh_all.description': '{jslang}wcf.igdb_integration.game.refresh_all.description{/jslang}',
			'wcf.igdb_integration.game.refresh_all.stale_only': '{jslang}wcf.igdb_integration.game.refresh_all.stale_only{/jslang}',
			'wcf.igdb_integration.game.refresh_all.start': '{jslang}wcf.igdb_integration.game.refresh_all.start{/jslang}',
			'wcf.igdb_integration.game.refresh_all.nothing_to_do': '{jslang}wcf.igdb_integration.game.refresh_all.nothing_to_do{/jslang}',
			'wcf.igdb_integration.game.refresh_all.failed': '{jslang}wcf.igdb_integration.game.refresh_all.failed{/jslang}',
			'wcf.igdb_integration.game.refresh_all.progress_title': '{jslang}wcf.igdb_integration.game.refresh_all.progress_title{/jslang}',
			'wcf.igdb_integration.game.refresh_all.progress_finalize': '{jslang}wcf.igdb_integration.game.refresh_all.progress_finalize{/jslang}',
			'wcf.igdb_integration.dialog.steam_import_progress_batches': '{jslang __literal=true}wcf.igdb_integration.dialog.steam_import_progress_batches{/jslang}',
			'wcf.global.button.delete': '{jslang}wcf.global.button.delete{/jslang}',
			'wcf.global.success.delete': '{jslang}wcf.global.success.delete{/jslang}'
		});

		{if $canRefreshGames}
			setupRefreshAllButton('igdbIntegrationRefreshAllButton', '{unsafe:$gridView->getID()|encodeJS}_table', '{unsafe:$gameDeleteEndpoint|encodeJS}');
		{/if}
	});
</script>

{include file='footer'}
