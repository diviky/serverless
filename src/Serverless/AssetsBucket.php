<?php

namespace Diviky\Serverless\Serverless;

class AssetsBucket
{
    public function getResources(string $bucket)
    {
        $resources = [];
        $resources['AssetsBucket'] = [
            'Type' => 'AWS::S3::Bucket',
            'Properties' => [
                'BucketName' => $bucket,
            ],
        ];

        $resources['cloudFrontDistribution'] = [
            'Type' => 'AWS::S3::BucketPolicy',
            'Properties' => [
                'Bucket' => [
                    'Ref' => 'AssetsBucket',
                ],
                'PolicyDocument' => [
                    'Statement' => [
                        [
                            'Effect' => 'Allow',
                            'Principal' => [
                                'AWS' => [
                                    'Fn::Sub' => 'arn:aws:iam::cloudfront:user/CloudFront Origin Access Identity ${MyCloudFrontOAI}',
                                ],
                            ],
                            'Action' => 's3:GetObject',
                            'Resource' => [
                                ['Fn::Sub' => 'arn:aws:s3:::${AssetsBucket}/*'],
                                ['Fn::Sub' => 'arn:aws:s3:::${PrivateBucket2}/*'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $resources['cloudFrontOAI'] = [
            'Type' => 'AWS::CloudFront::CloudFrontOriginAccessIdentity',
            'Properties' => [
                'CloudFrontOriginAccessIdentityConfig' => [
                    'Comment' => 'CloudFront OAI for multiple buckets',
                ],
            ],
        ];

        $resources['S3BucketPolicy'] = [
            'Type' => 'AWS::CloudFront::Distribution',
            'Properties' => [
                'DistributionConfig' => [
                    'Enabled' => true,
                    'DefaultRootObject' => 'index.html',
                    'Origins' => [
                        [
                            'Id' => 'AssetsBucketOrigin',
                            'DomainName' => ['Fn::GetAtt' => ['AssetsBucket', 'DomainName']],
                            'S3OriginConfig' => [
                                'OriginAccessIdentity' => ['Fn::Sub' => 'origin-access-identity/cloudfront/${cloudFrontOAI}'],
                            ],
                        ],
                    ],
                    'DefaultCacheBehavior' => [
                        'TargetOriginId' => 'AssetsBucketOrigin',
                        'ViewerProtocolPolicy' => 'redirect-to-https',
                        'AllowedMethods' => ['GET', 'HEAD'],
                        'CachedMethods' => ['GET', 'HEAD'],
                        'ForwardedValues' => [
                            'QueryString' => false,
                            'Cookies' => ['Forward' => 'none'],
                        ],
                    ],
                    'ViewerCertificate' => [
                        'CloudFrontDefaultCertificate' => true,
                    ],
                ],
            ],
        ];

        return $resources;
    }
}
