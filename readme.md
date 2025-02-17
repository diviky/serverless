# Laravel Serverless Deployment

## npm packages

```
npm install -g serverless
serverless plugin install -n serverless-deployment-bucket
serverless plugin install -n serverless-dynamodb-autoscaling
serverless plugin install -n serverless-s3-sync
serverless plugin install -n serverless-plugin-scripts
serverless plugin install -n serverless-domain-manager
serverless plugin install -n serverless-lift
```

```
aws ecr get-login-password --region ap-south-1 | docker login --username AWS --password-stdin 872515265009.dkr.ecr.ap-south-1.amazonaws.com

docker push 872515265009.dkr.ecr.ap-south-1.amazonaws.com/serverless-airo-production:airo

```
