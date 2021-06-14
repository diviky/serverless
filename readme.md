# Laravel Vapor CLI

##
```
web
arn:aws:lambda:ap-south-1:959512994844:layer:vapor-php-74:13

```

### vapor login

```json
{
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIxIiwianRpIjoiZDM0ZWZmZDUyNDM2MDU2YTIwYzAzNWViMWVjNDlmNjg4Y2JhOWE2MWNlNDkwMTRlNzA2ODNjZWFiM2Y2NmExZjY4ODAxYWI3MTIzYjJmNjIiLCJpYXQiOjE2MDY5OTc1MzcsIm5iZiI6MTYwNjk5NzUzNywiZXhwIjoxNjM4NTMzNTM3LCJzdWIiOiIxODQ3MSIsInNjb3BlcyI6WyIqIl19.Prvm1kr5yYpC-TlJ9w9e8W7ev6OqaKC4DXUmKpEyqwJDJggUdi7to-Lks0PMJuRtErRzZ2s_jFz5tb_LxHPBqfo8fWQV2WzbZOgxiqUTtP15vCrGbTEIx3OXwtnE_ub-A8SlQVp1FfcidqPbHHS5KuCHjwWBLv01PvqaYJUJCuZnXYi0NVW1hPfsIZ9z9XWfoMdF5L4vKDlBoaDKPGeN2Er_Gcg9F9GL9cvDEKsSAUo7VlK6Ye6kO_6XpGYE-t4md8wI-W2vPYYMVZfjNKRALR2_r5u-Yrn2C5KP43pkLSB3ZExv1C8pehDv7eKQLOIjtGKzJBoML53Rlwgwfu5SFyOkAaeQu3EPot1P3YzStQ9AW2ceM7L5AP28S4WtXxQC7Gsoc3WhXedaHPPT0oM8-F5RqBh3Y8ZamzLN5G3XWejnBFyhFDyz5xA_C_qA2ztr4DnGyEmf9cQWVPECdi6kC8ipaLXQBU0ItzYbocMj7bsRDomsjUTzLX27gR-3uYamD_GCKlmS_JJmhbcjH_nT0OwPW5gULxcK7-VETq-rnre7RHHp6ERjQMetP5tmCuSVqDW5ZCOGuH74fCr19vpe43NU0EpPWo3cP77VmRrWbQZJ-_5CxUr2RsTxtcJdOO7mfEUUUzJgcvHJGEE1LCCs5r1TAneOgxV8WOW-og5VUaI"
}
```

```json
[
    {
        "id": 22655,
        "user_id": 18471,
        "name": "MOBtexting",
        "personal_team": true,
        "created_at": "2020-12-03T11:30:42+0000",
        "updated_at": "2020-12-03T11:43:05+0000",
        "owner": {
            "id": 18471,
            "name": "Sankar Suda",
            "email": "sankar@mobtexting.com",
            "email_verified_at": null,
            "current_team_id": 22655,
            "last_alert_read_at": null,
            "stripe_id": "cus_IVH1tNxR2xf37Z",
            "trial_ends_at": null,
            "created_at": "2020-12-03 11:30:42",
            "updated_at": "2020-12-03 12:00:03",
            "avatar_url": "https://www.gravatar.com/avatar/194e2c35bdf469b2ab4b83ab248c4188?d=https%3A%2F%2Fui-avatars.com%2Fapi%2FSankar%2BSuda",
            "has_address_information": false,
            "uses_two_factor_authentication": false
        }
    }
][
    {
        "id": 5548,
        "team_id": 22655,
        "uuid": "fffa4c62-5552-419f-907e-0ee6949e1369",
        "name": "MOBtexting",
        "type": "AWS",
        "role_arn": null,
        "role_sync": true,
        "sns_topic_arn": null,
        "network_limit": 5,
        "last_deleted_rest_api_at": null,
        "queued_for_deletion": false,
        "created_at": "2020-12-03T12:02:18+0000",
        "updated_at": "2020-12-03T12:02:18+0000"
    }
]
```

#### vapor init

```json
[
    {
        "id": 5548,
        "team_id": 22655,
        "uuid": "fffa4c62-5552-419f-907e-0ee6949e1369",
        "name": "MOBtexting",
        "type": "AWS",
        "role_arn": null,
        "role_sync": true,
        "sns_topic_arn": null,
        "network_limit": 5,
        "last_deleted_rest_api_at": null,
        "queued_for_deletion": false,
        "created_at": "2020-12-03T12:02:18+0000",
        "updated_at": "2020-12-03T12:02:18+0000"
    }
][
    {
        "id": 5548,
        "team_id": 22655,
        "uuid": "fffa4c62-5552-419f-907e-0ee6949e1369",
        "name": "MOBtexting",
        "type": "AWS",
        "role_arn": null,
        "role_sync": true,
        "sns_topic_arn": null,
        "network_limit": 5,
        "last_deleted_rest_api_at": null,
        "queued_for_deletion": false,
        "created_at": "2020-12-03T12:02:18+0000",
        "updated_at": "2020-12-03T12:02:18+0000"
    }
]
```

```json
{
    "project": {
        "name": "messaging",
        "cloud_provider_id": 5548,
        "region": "ap-south-1",
        "team_id": 22655,
        "updated_at": "2020-12-03T12:15:19+0000",
        "created_at": "2020-12-03T12:15:13+0000",
        "id": 16011,
        "bucket": "vapor-ap-south-1-1606997713",
        "asset_bucket": "vapor-ap-south-1-assets-1606997713",
        "cloudfront_id": "E2VUQFRWBWBJ2B",
        "cloudfront_domain": "d1zohpnh476ju6.cloudfront.net",
        "cloudfront_status": "pending",
        "cloud_provider": {
            "id": 5548,
            "team_id": 22655,
            "uuid": "fffa4c62-5552-419f-907e-0ee6949e1369",
            "name": "MOBtexting",
            "type": "AWS",
            "role_arn": "arn:aws:iam::097427577457:role/laravel-vapor-role",
            "role_sync": true,
            "sns_topic_arn": null,
            "network_limit": 5,
            "last_deleted_rest_api_at": null,
            "queued_for_deletion": false,
            "created_at": "2020-12-03T12:02:18+0000",
            "updated_at": "2020-12-03T12:15:13+0000",
            "networks": []
        },
        "environments": [],
        "asset_domains": {
            "cloudfront": null,
            "s3": "https://vapor-ap-south-1-assets-1606997713.s3.ap-south-1.amazonaws.com"
        },
        "last_deployed_at": null
    },
    "created_network": true
}
```

### vapor deploy production

```json
{
    "id": 16011,
    "team_id": 22655,
    "cloud_provider_id": 5548,
    "name": "messaging",
    "region": "ap-south-1",
    "bucket": "vapor-ap-south-1-1606997713",
    "asset_bucket": "vapor-ap-south-1-assets-1606997713",
    "cloudfront_id": "E2VUQFRWBWBJ2B",
    "cloudfront_domain": "d1zohpnh476ju6.cloudfront.net",
    "cloudfront_status": "pending",
    "github_repository": null,
    "queued_for_deletion": false,
    "created_at": "2020-12-03T12:15:13+0000",
    "updated_at": "2020-12-03T12:15:19+0000",
    "team": {
        "id": 22655,
        "user_id": 18471,
        "name": "MOBtexting",
        "personal_team": true,
        "created_at": "2020-12-03T11:30:42+0000",
        "updated_at": "2020-12-03T11:43:05+0000"
    },
    "cloud_provider": {
        "id": 5548,
        "team_id": 22655,
        "uuid": "fffa4c62-5552-419f-907e-0ee6949e1369",
        "name": "MOBtexting",
        "type": "AWS",
        "role_arn": "arn:aws:iam::097427577457:role/laravel-vapor-role",
        "role_sync": true,
        "sns_topic_arn": null,
        "network_limit": 5,
        "last_deleted_rest_api_at": null,
        "queued_for_deletion": false,
        "created_at": "2020-12-03T12:02:18+0000",
        "updated_at": "2020-12-03T12:15:13+0000"
    },
    "asset_domains": {
        "cloudfront": null,
        "s3": "https://vapor-ap-south-1-assets-1606997713.s3.ap-south-1.amazonaws.com"
    },
    "last_deployed_at": null
}
```
```
DYNAMODB_CACHE_TABLE=vapor_cache
FILESYSTEM_CLOUD=s3
FILESYSTEM_DRIVER=s3
HTTPS=on
LD_LIBRARY_PATH=/opt/lib:/opt/lib/bref:/lib64:/usr/lib64:/var/runtime:/var/runtime/lib:/var/task:/var/task/lib
PATH=/opt/bin:/usr/local/bin:/usr/bin/:/bin
LOG_CHANNEL=stack
LOG_STDERR_FORMATTER=Laravel\Vapor\Logging\JsonFormatter
MYSQL_ATTR_SSL_CA=/var/task/rds-combined-ca-bundle.pem
SCHEDULE_CACHE_DRIVER=dynamodb
SESSION_DRIVER=cookie
SESSION_LIFETIME=120
SQS_DELAY=3
SQS_PREFIX=https://sqs.ap-south-1.amazonaws.com/097427577457
SQS_QUEUE=messaging-production
SQS_TRIES=3
VAPOR_ARTIFACT_BUCKET_NAME=vapor-ap-south-1-1606997713
VAPOR_ARTIFACT_NAME=messaging-d0ce3ff0-a712-4844-b1fc-0e2ccbfcf689
VAPOR_MAINTENANCE_MODE=false
VAPOR_MAINTENANCE_MODE_SECRET	
VAPOR_SERVERLESS_DB=false
VAPOR_SSM_PATH=/messaging-production
VAPOR_SSM_VARIABLES=[]
APP_RUNNING_IN_CONSOLE=false
APP_VANITY_URL=https://snowy-leaf-uwtor0cwayfn.vapor-farm-a1.com
ASSET_URL=https://d1zohpnh476ju6.cloudfront.net/d0ce3ff0-a712-4844-b1fc-0e2ccbfcf689

#cli
APP_RUNNING_IN_CONSOLE=true
XDG_CONFIG_HOME=/tmp

```
