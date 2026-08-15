{capture assign='contentTitle'}{lang}wcf.igdb_integration.page.game_list_title{/lang} <span
	class="badge">{#$items}</span>{/capture}

{capture assign='headContent'}
	{if $pageNo < $pages}
		<link rel="next" href="{link controller='IgdbIntegrationGameList'}pageNo={unsafe:$pageNo+1}{/link}">
	{/if}
	{if $pageNo > 1}
		<link rel="prev" href="{link controller='IgdbIntegrationGameList'}{if $pageNo > 2}pageNo={unsafe:$pageNo-1}{/if}{/link}">
	{/if}
	<link rel="canonical" href="{link controller='IgdbIntegrationGameList'}{if $pageNo > 1}pageNo={unsafe:$pageNo}{/if}{/link}">
{/capture}

{capture assign='sidebarRight'}
	<section class="box">
		<form id="gameSortForm" method="post" action="{link controller='IgdbIntegrationGameList'}{/link}">
			<h2 class="boxTitle">{lang}wcf.global.search{/lang}</h2>

			<div class="boxContent">
				<div class="igdbIntegrationSearchInfo">
					{if $showIgdbError}
						<p class="error">{lang}wcf.igdb_integration.page.game_list_igdb_error{/lang}</p>
					{else}
						{lang}wcf.igdb_integration.page.game_list_info{/lang}
					{/if}
				</div>
				<dl>
					<dt>{lang}wcf.global.title{/lang}</dt>
					<dd>
						<input type="text" id="searchField" name="searchField" value="{$searchField}">
						{event name='searchField'}
					</dd>
					<dt>{lang}wcf.igdb_integration.game.platforms{/lang}</dt>
					<dd>
						<select id="platformFilter" name="platforms[]" multiple size="6">
							{foreach from=$availablePlatforms item=platform}
								<option value="{$platform}"{if $platform|in_array:$platformFilter} selected{/if}>{$platform}</option>
							{/foreach}
						</select>
						{event name='platformFilter'}
					</dd>
					<dt>{lang}wcf.global.sorting{/lang}</dt>
					<dd>
						<select id="sortField" name="sortField">
							<option value="displayName" {if $sortField == 'displayName'} selected{/if}>
								{lang}wcf.igdb_integration.game.name{/lang}</option>
							<option value="releaseYear" {if $sortField == 'releaseYear'} selected{/if}>
								{lang}wcf.igdb_integration.game.year{/lang}</option>
							<option value="playerCount" {if $sortField == 'playerCount'} selected{/if}>
								{lang}wcf.igdb_integration.game.players{/lang}</option>
							<option value="averageRating" {if $sortField == 'averageRating'} selected{/if}>
								{lang}wcf.form.field.rating{/lang}</option>
							{event name='sortField'}
						</select>
						<select name="sortOrder">
							<option value="ASC" {if $sortOrder == 'ASC'} selected{/if}>
								{lang}wcf.global.sortOrder.ascending{/lang}</option>
							<option value="DESC" {if $sortOrder == 'DESC'} selected{/if}>
								{lang}wcf.global.sortOrder.descending{/lang}</option>
						</select>
					</dd>
				</dl>

				<div class="formSubmit">
					<a href="{link controller='IgdbIntegrationGameList'}{/link}" class="button">{lang}wcf.global.button.reset{/lang}</a>
					<input type="submit" value="{lang}wcf.global.button.submit{/lang}" accesskey="s">
				</div>
			</div>
		</form>
	</section>
	<section id="playerToplistBox" class="box">
		<h2 class="boxTitle">{lang}wcf.igdb_integration.page.player_toplist{/lang}</h2>
		<div class="boxContent">
			<table>
				{foreach from=$topPlayers item=player}
					<tr>
						<td>
							<b>{unsafe:$topPlayerProfileLinks[$player['userId']]}</b>
						</td>
						<td>
							{$player['gameCount']} {lang}wcf.user.option.igdb_integration_game_count{/lang}
						</td>
					</tr>
				{/foreach}
			</table>
		</div>
	</section>
{/capture}

{include file='header'}

{hascontent}
<div class="paginationTop">
	{content}
	{pages print=true assign=pagesLinks controller='IgdbIntegrationGameList' link="pageNo=%d&searchField=$searchField&sortField=$sortField&sortOrder=$sortOrder$platformFilterParams"}
	{/content}
</div>
{/hascontent}

{if $items}
	<div class="section igdbIntegrationGameListContainer">
		{foreach from=$objects item=game}
			<div class="gameBox" id="gameBox{$game->gameId}">
				<div class="gameCover" style="background-image: url({$coverImageUrls[$game->gameId]});">
					<div class="gameOverlay" id="gameOverlay{$game->gameId}">
						{if $__wcf->user->userID && $__wcf->session->getPermission('user.igdb_integration.can_manage_own_games')}
							<span class="gameOverlayButton pointer" id="gameOverlayEdit{$game->gameId}"
								title="{lang}wcf.igdb_integration.dialog.game_user_edit_title{/lang}">
								{icon size=48 name='pen-to-square' type='solid'}
							</span>
							<span class="gameOverlayButton pointer" id="gameOverlayQuickAdd{$game->gameId}"
								data-is-owned="{if $game->isOwned == 1}1{else}0{/if}"
								title="{if $game->isOwned == 1}{lang}wcf.igdb_integration.page.game_quick_remove{/lang}{else}{lang}wcf.igdb_integration.page.game_quick_add{/lang}{/if}">
								{if $game->isOwned == 1}
									{icon size=48 name='minus' type='solid'}
								{else}
									{icon size=48 name='plus' type='solid'}
								{/if}
							</span>
						{else}
							<span class="gameOverlayButton pointer" id="gameOverlayEdit{$game->gameId}"
								title="{lang}wcf.igdb_integration.dialog.game_user_edit_title{/lang}">
								{icon size=48 name='circle-info' type='solid'}
							</span>
						{/if}
					</div>
				</div>
				<div class="gameInfo">
					<h3>{$game->displayName}</h3>
					<small>{$game->releaseYear}</small>
					<div class="gameUserInfo">
						<p class="gameAverageRating orange">
							{section name=ratingStars loop=$game->averageRating}{icon size=16 name='star' type='solid'}{/section}
						</p>
						<p class="gamePlayerCount pointer{if $game->isOwned == 1} isOwned{/if}"
							id="gamePlayerCount{$game->gameId}" {if $game->playerCount <= 0} style="display: none;" {/if}>
							{icon size=16 name='user' type='solid'} {$game->playerCount}
						</p>
					</div>
				</div>
			</div>
		{/foreach}
	</div>
{else}
	<p class="info">{lang}wcf.global.noItems{/lang}</p>
{/if}

<footer class="contentFooter igdbIntegrationFooter">
	{hascontent}
	<div class="paginationBottom">
		{content}{unsafe:$pagesLinks}{/content}
	</div>
	{/hascontent}

	{hascontent}
	<nav class="contentFooterNavigation">
		<ul>{content}{event name='contentFooterNavigation'}{/content}</ul>
	</nav>
	{/hascontent}

	{if IGDB_INTEGRATION_GENERAL_SHOW_BRANDING}
		<div class="igdbIntegrationCopyright">{lang}wcf.igdb_integration.page.copyright_info{/lang}</div>
	{/if}
</footer>

<script data-relocate="true">
	{foreach from=$objects item=game}
		require(['Language', 'WoltLabSuite/Core/Controller/IgdbIntegrationGameList'], (Language,
			ControllerIgdbIntegrationGameList) => {
			Language.addObject({
				'wcf.igdb_integration.dialog.game_user_edit_title': '{jslang}wcf.igdb_integration.dialog.game_user_edit_title{/jslang}',
				'wcf.igdb_integration.dialog.game_player_list_title': '{jslang}wcf.igdb_integration.dialog.game_player_list_title{/jslang}',
				'wcf.igdb_integration.page.game_quick_add': '{jslang}wcf.igdb_integration.page.game_quick_add{/jslang}',
				'wcf.igdb_integration.page.game_quick_remove': '{jslang}wcf.igdb_integration.page.game_quick_remove{/jslang}'
			})
			let gameId = {unsafe:$game->gameId};
			ControllerIgdbIntegrationGameList.init(gameId);
		});
	{/foreach}
</script>

{include file='footer'}