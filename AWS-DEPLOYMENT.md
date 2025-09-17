# Bix AWS Deployment Guide

This guide explains how to deploy Bix (formerly Pixelfed) entirely on AWS infrastructure.

## AWS Services Required

### 1. Amazon RDS (Database)
- **Service**: Amazon RDS for MySQL/MariaDB
- **Configuration**: 
  - Engine: MySQL 8.0 or MariaDB 10.6+
  - Instance Class: db.t3.micro (for testing) or db.t3.small+ (for production)
  - Storage: 20GB+ SSD
  - Multi-AZ: Recommended for production

### 2. Amazon ElastiCache (Redis)
- **Service**: Amazon ElastiCache for Redis
- **Configuration**:
  - Engine: Redis 6.2+
  - Node Type: cache.t3.micro (for testing) or cache.t3.small+ (for production)
  - Cluster Mode: Disabled (for simplicity)

### 3. Amazon S3 (File Storage)
- **Service**: Amazon S3
- **Configuration**:
  - Bucket for media files (images, videos)
  - Public read access for media files
  - Versioning: Optional
  - Lifecycle policies: Recommended for cost optimization

### 4. Amazon SES (Email - Optional)
- **Service**: Amazon Simple Email Service
- **Configuration**:
  - Verify your domain
  - Set up SMTP credentials
  - Configure SPF/DKIM records

## Environment Variables

Update your `.env` file with the following AWS-specific configurations:

```bash
# Application
APP_NAME="Bix"
APP_ENV="production"
APP_DEBUG="false"

# AWS S3 Configuration
PF_ENABLE_CLOUD=true
FILESYSTEM_CLOUD=s3
DANGEROUSLY_SET_FILESYSTEM_DRIVER=s3
AWS_ACCESS_KEY_ID=your-access-key-id
AWS_SECRET_ACCESS_KEY=your-secret-access-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-s3-bucket-name
AWS_URL=https://your-s3-bucket-name.s3.amazonaws.com

# Database (AWS RDS)
DB_CONNECTION=mysql
DB_HOST=your-rds-endpoint.region.rds.amazonaws.com
DB_PORT=3306
DB_DATABASE=bix
DB_USERNAME=bix
DB_PASSWORD=your-secure-password

# Redis (AWS ElastiCache)
REDIS_HOST=your-elasticache-endpoint.region.cache.amazonaws.com
REDIS_PORT=6379
REDIS_PASSWORD=

# Email (AWS SES - Optional)
MAIL_DRIVER=smtp
MAIL_HOST=email-smtp.us-east-1.amazonaws.com
MAIL_PORT=587
MAIL_USERNAME=your-ses-smtp-username
MAIL_PASSWORD=your-ses-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Bix"
```

## Deployment Steps

### 1. Set up AWS Infrastructure

1. **Create RDS Instance**:
   ```bash
   aws rds create-db-instance \
     --db-instance-identifier bix-db \
     --db-instance-class db.t3.micro \
     --engine mysql \
     --master-username bix \
     --master-user-password your-secure-password \
     --allocated-storage 20 \
     --db-name bix
   ```

2. **Create ElastiCache Cluster**:
   ```bash
   aws elasticache create-cache-cluster \
     --cache-cluster-id bix-redis \
     --cache-node-type cache.t3.micro \
     --engine redis \
     --num-cache-nodes 1
   ```

3. **Create S3 Bucket**:
   ```bash
   aws s3 mb s3://your-bix-media-bucket
   aws s3api put-bucket-policy \
     --bucket your-bix-media-bucket \
     --policy file://s3-bucket-policy.json
   ```

### 2. Deploy Application

1. **Use AWS-specific Docker Compose**:
   ```bash
   docker-compose -f docker-compose.aws.yml up -d
   ```

2. **Run Database Migrations**:
   ```bash
   docker-compose -f docker-compose.aws.yml exec web php artisan migrate
   ```

3. **Set up Storage Link**:
   ```bash
   docker-compose -f docker-compose.aws.yml exec web php artisan storage:link
   ```

## Security Considerations

1. **IAM Roles**: Use IAM roles instead of access keys when possible
2. **VPC**: Deploy in a private VPC with proper security groups
3. **SSL/TLS**: Use AWS Certificate Manager for SSL certificates
4. **Secrets**: Store sensitive data in AWS Secrets Manager
5. **Backup**: Enable automated backups for RDS and S3 versioning

## Monitoring and Logging

1. **CloudWatch**: Set up CloudWatch for application and infrastructure monitoring
2. **CloudTrail**: Enable CloudTrail for API logging
3. **Application Logs**: Configure log shipping to CloudWatch Logs

## Cost Optimization

1. **Reserved Instances**: Use Reserved Instances for predictable workloads
2. **S3 Lifecycle**: Set up S3 lifecycle policies for old media files
3. **Auto Scaling**: Implement auto scaling for EC2 instances
4. **Spot Instances**: Consider Spot Instances for non-critical workloads

## Troubleshooting

### Common Issues

1. **Database Connection**: Check security groups and VPC configuration
2. **S3 Access**: Verify IAM permissions and bucket policies
3. **Redis Connection**: Ensure ElastiCache is in the same VPC
4. **SSL Issues**: Check certificate configuration and domain settings

### Useful Commands

```bash
# Check application logs
docker-compose -f docker-compose.aws.yml logs web

# Check worker logs
docker-compose -f docker-compose.aws.yml logs worker

# Run artisan commands
docker-compose -f docker-compose.aws.yml exec web php artisan [command]

# Access application shell
docker-compose -f docker-compose.aws.yml exec web bash
```

## Support

For AWS-specific deployment issues, refer to:
- [AWS Documentation](https://docs.aws.amazon.com/)
- [Bix Community Forums](https://github.com/your-repo/discussions)
- [AWS Support](https://aws.amazon.com/support/)