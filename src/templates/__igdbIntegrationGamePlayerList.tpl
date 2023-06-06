<table class="playerList">
    {foreach from=$gameOwners item=owner}
        <tr>
            <td style="padding-right: 1rem;">
                <b><a href="/user/{$owner['userId']}">{$owner['username']}</a></b>
            </td>
            <td>
                {section name=ratingStars loop=$owner['rating']}
                    <span class="icon icon16 fa-star orange"></span>
                {/section}
            </td>
        </tr>
    {/foreach}
</table>

<style>
    table.playerList td {
        padding-right: 0.5rem;
        padding-bottom: 0.5rem;
    }
</style>