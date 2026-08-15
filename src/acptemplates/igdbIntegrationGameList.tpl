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

{include file='footer'}
