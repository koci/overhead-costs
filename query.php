SELECT 
    TO_VARCHAR(act."Date Created", 'DD.MM.YYYY') AS "Date Created",
    TO_VARCHAR(act."Date Posting", 'DD.MM.YYYY') AS "Date Posting",
    act."Order ID",     
    ordd."Order Name",
    ordd."Order Type ID Name",     
    act."Person ID",
    act."Operation ID",     
    op."Operation Text",
    op."Order Sequence Operation ID Text",
    op."Order Operation ID Text",
    op."Work Center Name",
    op."Work Center ID Name",     
    per."Person Name",
    per."Person Cost Center ID Name",
    per."Person Group Name",
    CASE 
        WHEN per."Person Cost Center ID Name" LIKE '%MP%' THEN 'Mirna Peč'
        ELSE 'Sora'
    END AS "Location",
    SUM(act."M Labor ACT H") AS "M Hours"

FROM "BXBI"."vFT PP Order Activities" act

LEFT JOIN "BXBI"."vMD XA Order Operation" op
    ON act."Order ID" = op."Order ID"
    AND act."Operation ID" = op."Operation ID"

LEFT JOIN "BXBI"."vMD HR Person" per
    ON act."Person ID" = per."Person ID" 
    
LEFT JOIN "BXBI"."vMD XA Order Data" ordd
    ON act."Order ID" = ordd."Order ID" 

WHERE act."Order ID" IN('1167362','1167363','1167364','1167365','1167366','1167367','1167368','1167369', '1167370','1167371','1167372','1167373','1167374')
    AND act."Report Data Version" = 'Actual' 
    
GROUP BY
    TO_VARCHAR(act."Date Created", 'DD.MM.YYYY'),
    TO_VARCHAR(act."Date Posting", 'DD.MM.YYYY'),
    act."Order ID",
    act."Person ID",
    act."Operation ID",     
    op."Operation Text",
    op."Order Sequence Operation ID Text",
    op."Order Operation ID Text",
    op."Work Center Name",
    op."Work Center ID Name",     
    per."Person Name",
    per."Person Cost Center ID Name",
    per."Person Group Name",
    ordd."Order Name",
    ordd."Order Type ID Name"