{include file='header' pageTitle='wcf.igdb_integration.page.game_'|concat:$action|concat:'_title'}

<header class="contentHeader">
	<div class="contentHeaderTitle">
		<h1 class="contentTitle">{lang}wcf.igdb_integration.page.game_{$action}_title{/lang}</h1>
	</div>

	<nav class="contentHeaderNavigation">
		<ul>
			<li><a href="{link controller='IgdbIntegrationGameList'}{/link}" class="button">{icon size=16 name='list' type='solid'}
					<span>{lang}wcf.acp.menu.link.igdb_integration.game_list{/lang}</span></a></li>

			{event name='contentHeaderNavigation'}
		</ul>
	</nav>
</header>

{@$form->getHtml()}

{include file='footer'}