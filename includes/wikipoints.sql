CREATE TABLE /*_*/wikipoints (
    actor INT UNSIGNED PRIMARY KEY,
    name VARBINARY(255),
    blocked BOOLEAN,
    points BIGINT
);
INSERT into wikipoints (actor, name, blocked, points)
SELECT actor_id AS actor, actor_name AS name, 0 AS blocked, 0 AS wikipoints FROM `actor`;
UPDATE wikipoints wp
SET blocked = 1
WHERE EXISTS (
    SELECT 1
    FROM   block_target bt
    WHERE  bt.bt_user = wp.actor
);
UPDATE wikipoints wp
JOIN (
        SELECT r.rev_actor AS actor, SUM( r.rev_len - COALESCE( p.rev_len, 0 ) ) AS points
        FROM revision r
        LEFT JOIN revision p ON r.rev_parent_id = p.rev_id
        WHERE NOT EXISTS (
                SELECT 1
                FROM change_tag      ct
                JOIN change_tag_def ctd ON ctd.ctd_id = ct.ct_tag_id
                WHERE ct.ct_rev_id = r.rev_id
                  AND ctd.ctd_name IN ( 'mw-reverted', 'mw-rollback', 'mw-undo' )
              )
        GROUP BY r.rev_actor
     ) AS src
   ON wp.actor = src.actor
SET wp.points = src.points;
