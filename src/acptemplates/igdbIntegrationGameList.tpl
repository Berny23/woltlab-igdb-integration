{include file='header' pageTitle='wcf.igdb_integration.page.game_list_title'}

<header class="contentHeader">
	<div class="contentHeaderTitle">
		<h1 class="contentTitle">{lang}wcf.igdb_integration.page.game_list_title{/lang}</h1>
		<p class="contentHeaderDescription">{lang}wcf.igdb_integration.page.acp_game_list_subtitle{/lang}</p>
	</div>

	<nav class="contentHeaderNavigation">
		<ul>
			{event name='contentHeaderNavigation'}
		</ul>
	</nav>
</header>

<div class="section">
	{unsafe:$gridView->render()}
</div>

<script data-relocate="true">
	require(['Language'], (Language) => {
		Language.addObject({
			'wcf.igdb_integration.game.refresh.missing_title': '{jslang}wcf.igdb_integration.game.refresh.missing_title{/jslang}',
			'wcf.igdb_integration.game.refresh.missing_message': '{jslang}wcf.igdb_integration.game.refresh.missing_message{/jslang}',
			'wcf.global.button.delete': '{jslang}wcf.global.button.delete{/jslang}',
			'wcf.global.success.delete': '{jslang}wcf.global.success.delete{/jslang}'
		});
	});
</script>

{include file='footer'}
