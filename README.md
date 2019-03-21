# local_dynamicaudience

## Maturity
stable

## Description
Configure audience (cohort) membership based on data including
- user account information
- course completion and enrolment
- cohort membership

Cohort membership is calculated globally when the cohort rules are changed.
Cohort membership is calculated for an individual when that user's account,
course completion or cohort membership data change.

This plugin is event-based, not run on a cron job.
