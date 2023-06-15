<dl class="igdbIntegrationPlayerListContainer">
	<dt>
		<h2>{lang}wcf.igdb_integration.dialog.game_player_list_title{/lang}</h2>
	</dt>
	<dd>
		<table class="playerList">
			{foreach from=$gameOwners item=owner}
				<tr>
					<td>
						<b>{@$gameOwnerProfileLinks[$owner['userId']]}</b>
					</td>
					<td>
						{section name=ratingStars loop=$owner['rating']}<span
							class="icon icon16 fa-star orange"></span>{/section}
					</td>
				</tr>
			{/foreach}
		</table>
	</dd>
</dl>