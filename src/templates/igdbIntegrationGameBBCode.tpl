<div class="igdbIntegrationGameEmbed embeddedContent{if $authorOwns} igdbIntegrationGameEmbedAuthorOwns{/if}"
	data-game-id="{$game->gameId}"
	data-game-title="{$displayName}{if $game->releaseYear} ({$game->releaseYear}){/if}"
	data-author-is-current-user="{if $authorIsCurrentUser}1{else}0{/if}">
	<span class="igdbIntegrationGameEmbedOwnedBadge" title="{lang}wcf.igdb_integration.bbcode.author_owns{/lang}">
		{icon size=24 name='user-check' type='solid'}
	</span>
	<img class="igdbIntegrationGameEmbedCover" src="{$coverImageUrl}" alt="" loading="lazy">
	<div class="igdbIntegrationGameEmbedInfo">
		<h3 class="igdbIntegrationGameEmbedTitle">{$displayName}</h3>
		<small class="igdbIntegrationGameEmbedYear">{$game->releaseYear}</small>
		{if $game->platforms}
			<small class="igdbIntegrationGameEmbedPlatforms">{$game->platforms}</small>
		{/if}
		{if $gameUrl}
			<a href="{$gameUrl}" class="externalURL igdbIntegrationGameEmbedLink"{if EXTERNAL_LINK_TARGET_BLANK} target="_blank"{/if}
				rel="nofollow noopener">{lang}wcf.igdb_integration.dialog.game_igdb_link{/lang}</a>
		{/if}
		{if $authorOwns && $authorRating > 0}
			<p class="igdbIntegrationGameEmbedAuthorRating orange" title="{lang}wcf.igdb_integration.bbcode.author_rating{/lang}">
				{section name=ratingStars loop=$authorRating}{icon size=16 name='star' type='solid'}{/section}
			</p>
		{/if}
		<div class="igdbIntegrationGameEmbedButtons">
			{if $__wcf->user->userID && $__wcf->session->getPermission('user.igdb_integration.can_manage_own_games')}
				<button type="button" class="button small igdbIntegrationGameEmbedEdit"
					title="{lang}wcf.igdb_integration.dialog.game_user_edit_title{/lang}">
					{icon size=16 name='pen-to-square' type='solid'}
				</button>
				<button type="button" class="button small igdbIntegrationGameEmbedQuickAdd"
					data-is-owned="{if $isOwned}1{else}0{/if}"
					title="{if $isOwned}{lang}wcf.igdb_integration.page.game_quick_remove{/lang}{else}{lang}wcf.igdb_integration.page.game_quick_add{/lang}{/if}">
					{if $isOwned}
						{icon size=16 name='minus' type='solid'}
					{else}
						{icon size=16 name='plus' type='solid'}
					{/if}
				</button>
			{else}
				<button type="button" class="button small igdbIntegrationGameEmbedEdit"
					title="{lang}wcf.igdb_integration.dialog.game_user_edit_title{/lang}">
					{icon size=16 name='circle-info' type='solid'}
				</button>
			{/if}
			<button type="button" class="button small igdbIntegrationGameEmbedPlayerList"
				title="{lang}wcf.igdb_integration.dialog.game_player_list_title{/lang}">
				{icon size=16 name='user' type='solid'}
				<span class="igdbIntegrationGameEmbedPlayerCount">{$game->playerCount}</span>
			</button>
		</div>
	</div>
</div>
<script data-relocate="true">
	require(['Language', 'WoltLabSuite/Core/Controller/IgdbIntegrationGameEmbed'], (Language,
		ControllerIgdbIntegrationGameEmbed) => {
		Language.addObject({
			'wcf.igdb_integration.dialog.game_user_edit_title': '{jslang}wcf.igdb_integration.dialog.game_user_edit_title{/jslang}',
			'wcf.igdb_integration.dialog.game_player_list_title': '{jslang}wcf.igdb_integration.dialog.game_player_list_title{/jslang}',
			'wcf.igdb_integration.page.game_quick_add': '{jslang}wcf.igdb_integration.page.game_quick_add{/jslang}',
			'wcf.igdb_integration.page.game_quick_remove': '{jslang}wcf.igdb_integration.page.game_quick_remove{/jslang}'
		});
		ControllerIgdbIntegrationGameEmbed.setup();
	});
</script>
