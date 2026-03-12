<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Auto generated seed file
     */
    public function run(): void
    {
        $auditUserId = DB::table('users')->orderBy('id')->value('id');

        $templates = [
            0 => [
                'id' => 1,
                'name' => 'Welcome Message [Sent after Sign Up]',
                'subject' => 'Welcome to {app_name} - Connecting Pups with Loving Homes',
                'message' => '<p><strong>Dear {contact_firstname},</strong></p><p>Welcome to {app_name} - the online directory where you can connect with loving homes for your furry best friends. Our mission is to make the process of buying and selling puppies easy, secure, and enjoyable.</p><p>At {app_name}, we understand the special bond between a pet and its owner. Our platform has been designed with this in mind, and we aim to provide a safe and user-friendly space for you to find your perfect pup or to find a loving home for your puppies.</p><p>Our directory is filled with various breeds, each with its unique personality and qualities. Whether you are looking for a loyal companion or a playful partner, you will find a furry friend that will steal your heart.</p><p>We encourage you to explore our directory, connect with other community members, and find the perfect match for you and your furry friend. We look forward to hearing your feedback and helping you find the perfect pup!</p><p>Thank you for choosing {app_name}. We hope you enjoy your experience.</p><p>{email_signature}</p>',
                'send_to' => '',
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2023-07-15 11:06:24',
                'updated_at' => '2023-07-15 13:36:29',
                'deleted_at' => null,
            ],
            1 => [
                'id' => 2,
                'name' => 'Email Verification (Send to Contact After Registration)',
                'subject' => 'Please verify your email address',
                'message' => '<p>Dear {contact_firstname},</p><p>Thank you for signing up! We\'re thrilled to have you in our community, especially knowing you share our love for dogs.</p><p>To complete the verification process and ensure the security of your account, please click on the verification link below:</p><p><a href="{email_verification_url}">Verify Email Address</a></p><p>If you\'re having trouble, try copying and pasting the following URL into your browser</p><p>{email_verification_url}</p><p>Thank you again for joining us.&nbsp;</p><p>{email_signature}</p><p>PS: No further action is required if you did not create an account.<br>&nbsp;</p>',
                'send_to' => '',
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2023-07-15 11:06:24',
                'updated_at' => '2023-07-15 13:36:29',
                'deleted_at' => null,
            ],
            2 => [
                'id' => 3,
                'name' => 'New Contact Added/Registered (Welcome Email)',
                'subject' => 'Welcome aboard',
                'message' => '<p>Dear {contact_firstname} {contact_lastname}</p><p>&nbsp;</p><p>Thank you for registering on the {companyname} CRM System.</p><p>&nbsp;</p><p>We just wanted to say welcome.</p><p>&nbsp;</p><p>Please contact us if you need any help.</p><p>&nbsp;</p><p>Click here to view your profile: {<a href="{crm_url}">{crm_url}</a>}</p><p>&nbsp;</p><p>Kind Regards,</p><p>{email_signature}</p><p>&nbsp;</p><p>(This is an automated email, so please don\'t reply to this email address)</p>',
                'send_to' => null,
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2023-07-15 11:06:24',
                'updated_at' => '2023-07-15 13:36:29',
                'deleted_at' => null,
            ],
            3 => [
                'id' => 4,
                'name' => 'Set New Password',
                'subject' => 'Set new password on {app_name}',
                'message' => '<p>Dear {contact_firstname},</p><p>We\'ve received your request to reset your password on {app_name}. To ensure the security of your account, we require you to set up a new password. Please use the link below to create a new password:</p><p><a href="{set_password_url}">Set new password</a></p><p>Please keep this link safe and secure, as it will expire in 48 hours. If you do not reset your password within this time frame, you must request a new link.</p><p>After you have set your new password, you can log in to your account at <a href="{app_url}">{app_url}</a>. If you have any issues logging in or need further assistance, don\'t hesitate to contact us.</p><p>Thank you for using {app_name}. We value your membership and are here to support you every step of the way.</p><p>{email_signature}</p>',
                'send_to' => '',
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2023-07-15 11:06:24',
                'updated_at' => '2023-07-15 13:36:29',
                'deleted_at' => null,
            ],
            4 => [
                'id' => 5,
                'name' => 'Forget Password',
                'subject' => 'Set New Password on {app_name}',
                'message' => '<p>Dear {contact_firstname},</p><p>We\'ve received your request to reset your password on {app_name}. To ensure the security of your account, we require you to set up a new password. Please use the link below to create a new password:</p><p><a href="{set_password_url}">Set new password</a></p><p>Please keep this link safe and secure, as it will expire in 2 hours. You must request a new link if you do not reset your password within this time frame.</p><p>After you have set your new password, you can log in to your account at <a href="{app_url}">{app_url}</a>. If you have any issues logging in or need further assistance, don\'t hesitate to contact us.</p><p>Thank you for using {app_name}. We value your membership and are here to support you every step of the way.</p><p>{email_signature}</p>',
                'send_to' => '',
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2023-07-15 11:06:24',
                'updated_at' => '2023-07-15 13:36:29',
                'deleted_at' => null,
            ],
            5 => [
                'id' => 6,
                'name' => 'Contact Form Email',
                'subject' => 'Contact Form Email | {app_name}',
                'message' => '<p>Hello Admin</p>
                <p>Below are contact form information from the website</p>
                <p>----------------------------------------------</p>
                <p>Name : {name}</p>
                <p>Email Address : {email}</p>
                <p>Message : {message}</p>
                <p>----------------------------------------------</p>',
                'send_to' => '',
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2023-07-15 11:06:24',
                'updated_at' => '2023-07-26 11:43:08',
                'deleted_at' => null,
            ],
            6 => [
                'id' => 7,
                'name' => 'Message to Author',
                'subject' => 'New Message from {message_name}',
                'message' => '<p>Dear {contact_firstname},</p><p>You have received a new message.</p><p>Below are the details</p><p>Ad ID : {ad_id}</p><p>Ad Title : {message_title}</p><p>Ad URL :&nbsp;<a href="{message_url}">{message_url}</a></p><p>Name : {message_name}</p><p>Email Address : {message_email}</p><p>Phone Number : {message_phone}</p><p>Message : {message_message}</p><p>{email_signature}</p>',
                'send_to' => '',
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2023-07-15 11:06:24',
                'updated_at' => '2023-07-15 11:06:24',
                'deleted_at' => null,
            ],
            7 => [
                'id' => 9,
                'name' => 'Thank you email to Subscriber',
                'subject' => 'Thank you for Subscribe',
                'message' => '<p>Hi,</p><p>Thank you for subscribe&nbsp;</p><p>{email_signature}</p>',
                'send_to' => null,
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2023-07-15 11:06:24',
                'updated_at' => '2023-07-15 13:36:29',
                'deleted_at' => null,
            ],
            8 => [
                'id' => 11,
                'name' => 'Direct Message To Author',
                'subject' => 'Message from Customer',
                'message' => '<p>Hello {breeder_first_name},</p><p>Someone is trying to contact you.</p><p>Below are the details</p><p>Name : {message_name}</p><p>Email Address : {message_email}</p><p>Phone Number : {message_phone}</p><p>Message : {message_message}</p><p>{email_signature}</p>',
                'send_to' => '',
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2023-07-15 11:06:24',
                'updated_at' => '2023-07-15 11:06:24',
                'deleted_at' => null,
            ],
            9 => [
                'id' => 12,
                'name' => 'Password Reset Mail to all Users',
                'subject' => 'Password Expired',
                'message' => '<p>Looks like your password is expired, reset your password to continue surfing on our platform</p><p>To reset your password, please click on the link below.</p><p><a href="{reset_password_url}">Reset your password</a></p><p>If you\'re having trouble, try copying and pasting the following URL into your browser:<br>{reset_password_url}</p><p>If you did not request this reset, you can ignore this email. It will expire in 2 hours time.</p><p>&nbsp;</p><p>{email_signature}</p>',
                'send_to' => null,
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2023-07-15 11:06:24',
                'updated_at' => '2023-07-15 11:06:24',
                'deleted_at' => null,
            ],
            10 => [
                'id' => 13,
                'name' => 'New User Registration (Send to admins)',
                'subject' => 'New User Registration',
                'message' => '<p>Hello Admin.</p><p>A new user has registred. Below are the details:</p><p>First Name:&nbsp;{firstname}</p><p>Last Name:&nbsp;{lastname}</p><p>Email:&nbsp;{email}</p><p>View profile: <a href="{profile_url}">{profile_url}</a></p><p>{email_signature}</p>',
                'send_to' => '',
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2023-07-15 11:06:24',
                'updated_at' => '2023-07-15 13:36:29',
                'deleted_at' => null,
            ],
            11 => [
                'id' => 14,
                'name' => 'New Subscriber (Send to admins)',
                'subject' => 'You got a new subscriber.',
                'message' => '<p>Hi Admin,</p><p>You got a new subscriber on your portal.</p><p>Email Id : {email}</p><p>{email_signature}</p>',
                'send_to' => '',
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2023-07-15 11:06:24',
                'updated_at' => '2023-07-15 13:36:29',
                'deleted_at' => null,
            ],
            12 => [
                'id' => 15,
                'name' => 'New Ad Submitted (Send to admins)',
                'subject' => 'New Ad Submitted',
                'message' => '<p>Hi Admin,</p><p>New ad has been posted. Below are the details:</p><p>Ad Title: {ad_title}</p><p>Posted on: {created_at}</p><p>Visit view page url: <a href="{view_url}">{view_url}</a></p><p>{email_signature}</p>',
                'send_to' => '',
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2023-07-15 11:06:24',
                'updated_at' => '2023-07-24 19:00:07',
                'deleted_at' => null,
            ],
            13 => [
                'id' => 16,
                'name' => 'New Ad Flagged (Sent to Admins)',
                'subject' => 'New Ad Flagged',
                'message' => '<p>Hi Admin,</p><p>A new ad has been flagged. Below are the details:</p><p>Ad Title: {ad_title}</p><p>Report Type: {report_type}</p><p>Report Comment: {report_comment}</p><p>Click here to visit ad - <a href="{front_url}">{front_url}</a></p><p>{email_signature}</p>',
                'send_to' => '',
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2023-07-15 11:06:24',
                'updated_at' => '2023-07-24 19:00:07',
                'deleted_at' => null,
            ],
            14 => [
                'id' => 17,
                'name' => 'Ad Status Updated (Send to admins)',
                'subject' => 'Ad Status Updated - {ad_id}',
                'message' => '<p>Hi Admin,</p><p>Ad status updated:</p><p>Ad Title: {ad_title}</p><p>Ad ID: {ad_id}</p><p>Old Status: {old_status}</p><p>Updated Status: {updated_status}</p><p>Updated By: {updated_by}</p><p>Manage Ad - <a href="{view_url}">{view_url}</a></p><p>{email_signature}</p>',
                'send_to' => '',
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2023-07-15 11:06:24',
                'updated_at' => '2023-07-24 19:00:07',
                'deleted_at' => null,
            ],
            15 => [
                'id' => 18,
                'name' => 'Message to Posted By',
                'subject' => 'New Message from {message_name}',
                'message' => '<p>Hi {posted_by_name},</p>
                <p>ou have received a new message.</p>
                <p>Below are the details</p>
                <table style="border-collapse: collapse; width: 100%; height: 156.734px;" border="1">
                <tbody>
                <tr style="height: 22.3906px;">
                <td style="width: 16.7024%; height: 22.3906px;">Ad Title</td>
                <td style="width: 83.2976%; height: 22.3906px;">{message_title}</td>
                </tr>
                <tr style="height: 22.3906px;">
                <td style="width: 16.7024%; height: 22.3906px;">Ad ID</td>
                <td style="width: 83.2976%; height: 22.3906px;">{ad_id}</td>
                </tr>
                <tr style="height: 22.3906px;">
                <td style="width: 16.7024%; height: 22.3906px;">Ad URL</td>
                <td style="width: 83.2976%; height: 22.3906px;"><a href="{message_url}">{message_url}</a></td>
                </tr>
                <tr style="height: 22.3906px;">
                <td style="width: 16.7024%; height: 22.3906px;">Name&nbsp;</td>
                <td style="width: 83.2976%; height: 22.3906px;">{message_name}</td>
                </tr>
                <tr style="height: 22.3906px;">
                <td style="width: 16.7024%; height: 22.3906px;">Email</td>
                <td style="width: 83.2976%; height: 22.3906px;">{message_email}</td>
                </tr>
                <tr style="height: 22.3906px;">
                <td style="width: 16.7024%; height: 22.3906px;">Mobile</td>
                <td style="width: 83.2976%; height: 22.3906px;">{message_phone}</td>
                </tr>
                <tr style="height: 22.3906px;">
                <td style="width: 16.7024%; height: 22.3906px;">Message</td>
                <td style="width: 83.2976%; height: 22.3906px;">{message_message}</td>
                </tr>
                </tbody>
                </table>
                <p><br />{email_signature}</p>',
                'send_to' => '',
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2023-07-15 11:06:24',
                'updated_at' => '2023-07-24 19:00:07',
                'deleted_at' => null,
            ],
            16 => [
                'id' => 19,
                'name' => 'New Ad Submitted (Send to user)',
                'subject' => 'Your Ad has been submitted',
                'message' => '<p>Hi {user_name},</p><p>Your ad has been submitted for approval. Below are the details:</p><p>Ad Title: {ad_title}</p><p>Submitted on: {submitted_at}</p><p>Visit view page url: <a href="{view_url}">{view_url}</a></p><p>{email_signature}</p>',
                'send_to' => '',
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2023-07-15 11:06:24',
                'updated_at' => '2023-07-24 19:00:07',
                'deleted_at' => null,
            ],
            17 => [
                'id' => 20,
                'name' => 'Ad Status Updated (Send to user)',
                'subject' => 'Ad Status Updated - {ad_id}',
                'message' => '<p>Hi {user_name},</p><p>Your Ad status has been {updated_status}. Check Below details:</p><p>Ad Title: {ad_title}</p><p>Ad ID: {ad_id}</p><p>Old Status: {old_status}</p><p>Updated Status: {updated_status}</p><p>Updated By: {updated_by}</p><p>Visit view page url: <a href="{view_url}">{view_url}</a></p><p>{email_signature}</p>',
                'send_to' => '',
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2023-07-15 11:06:24',
                'updated_at' => '2023-07-24 19:00:07',
                'deleted_at' => null,
            ],
            18 => [
                'id' => 21,
                'name' => 'Website Setup Completion Email (send to user)',
                'subject' => 'Your website setup completed | [{domain}]',
                'message' => '<p>Hi {first_name},</p>
                <p>Great news! Your website <strong>{domain}</strong> is now ready to go live!</p>
                <h3>Your Website Details</h3>
                <table style="border-collapse: collapse; width: 100%; margin-bottom: 20px;" border="1">
                <tbody>
                <tr><td style="padding: 10px; width: 200px;"><strong>Website URL</strong></td><td style="padding: 10px;"><a href="{website_url}" target="_blank">{website_url}</a></td></tr>
                <tr><td style="padding: 10px;"><strong>Admin Panel</strong></td><td style="padding: 10px;"><a href="{backend_url}" target="_blank">{backend_url}</a></td></tr>
                </tbody>
                </table>
                <h3>Your Login Credentials</h3>
                <table style="border-collapse: collapse; width: 100%; margin-bottom: 20px;" border="1">
                <tbody>
                <tr><td style="padding: 10px; width: 200px;"><strong>Email</strong></td><td style="padding: 10px;">{email}</td></tr>
                <tr><td style="padding: 10px;"><strong>Temporary Password</strong></td><td style="padding: 10px;">{password}</td></tr>
                </tbody>
                </table>
                <p style="background-color: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;"><strong>⚠️ Security Reminder:</strong> Please change your password after your first login. Go to <strong>Profile → Security</strong> to update your password.</p>
                <p>If you need any help, contact our support team at <a href="mailto:support@astero.in">support@astero.in</a>.</p>
                <p>Looking forward to seeing your website flourish!</p>
                <p>{email_signature}</p>',
                'send_to' => '',
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2023-07-15 11:06:24',
                'updated_at' => '2023-07-28 20:02:04',
                'deleted_at' => null,
            ],
            19 => [
                'id' => 22,
                'name' => 'Website Setup Completion Email (send to admin)',
                'subject' => 'New website setup completed | [{domain}]',
                'message' => '<p>Hello Admin,</p>
                <p>A new website <strong>{domain}</strong> has been provisioned successfully.</p>
                <h3>Website Details</h3>
                <table style="border-collapse: collapse; width: 100%; margin-bottom: 20px;" border="1">
                <tbody>
                <tr><td style="padding: 10px; width: 200px;"><strong>Website URL</strong></td><td style="padding: 10px;"><a href="{website_url}" target="_blank">{website_url}</a></td></tr>
                <tr><td style="padding: 10px;"><strong>Admin Panel</strong></td><td style="padding: 10px;"><a href="{backend_url}" target="_blank">{backend_url}</a></td></tr>
                </tbody>
                </table>
                <h3>Super User Credentials</h3>
                <table style="border-collapse: collapse; width: 100%; margin-bottom: 20px;" border="1">
                <tbody>
                <tr><td style="padding: 10px; width: 200px;"><strong>Email</strong></td><td style="padding: 10px;">{email}</td></tr>
                <tr><td style="padding: 10px;"><strong>Temporary Password</strong></td><td style="padding: 10px;">{password}</td></tr>
                </tbody>
                </table>
                <p style="background-color: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;"><strong>⚠️ Security Reminder:</strong> Please change your password after first login via <strong>Profile → Security</strong>.</p>
                <p>{email_signature}</p>',
                'send_to' => '',
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2023-07-15 11:06:24',
                'updated_at' => '2023-07-28 20:02:20',
                'deleted_at' => null,
            ],
            20 => [
                'id' => 23,
                'name' => 'New Support Ticket Opened',
                'subject' => 'New Support Ticket Opened',
                'message' => '<p>Hi {client_name},</p>
                <p>Thank you for contacting our support team. A support ticket has now been opened for your request. You will be notified when a response is made by email. The details of your ticket are shown below.</p>
                <p>Subject: {ticket_subject}</p>
                <p>Priority: {ticket_priority}</p>
                <p>Status: {ticket_status}</p>
                <p>You can view the ticket at any time at the given link</p>
                <p><a href="{view_url}">{view_url}</a></p>
                <p>{email_signature}</p>',
                'send_to' => '',
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2023-07-15 11:06:24',
                'updated_at' => '2023-07-24 19:00:07',
                'deleted_at' => null,
            ],
            21 => [
                'id' => 24,
                'name' => 'Support Ticket Response',
                'subject' => 'Support Ticket Response',
                'message' => '<p>{ticket_message}</p>
                <p>----------------------------------------------</p>
                <p>Ticket ID: #{ticket_id}</p>
                <p>Subject: {ticket_subject}</p>
                <p>Status: {ticket_status}</p>
                <p>Ticket URL: <a href="{view_url}">{view_url}</a></p>
                <p>----------------------------------------------</p>
                <p>{email_signature}</p>',
                'send_to' => '',
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2023-07-15 11:06:24',
                'updated_at' => '2023-07-24 19:00:07',
                'deleted_at' => null,
            ],
            22 => [
                'id' => 25,
                'name' => 'User Credentials',
                'subject' => 'Welcome to {app_name}',
                'message' => '<p>Welcome to {app_name}!<br /><br />A new account has been created for you. Please use the following credentials to login.<br /><br /></p>
                <table style="border-collapse: collapse; width: 100%;" border="1">
                <tbody>
                <tr>
                <td style="width: 21.9446%;"><strong>Email :</strong></td>
                <td style="width: 78.2409%;">{email}</td>
                </tr>
                <tr>
                <td style="width: 21.9446%;"><strong>Password :</strong></td>
                <td style="width: 78.2409%;">{password}</td>
                </tr>
                <tr>
                <td style="width: 21.9446%;"><strong>URL :</strong></td>
                <td style="width: 78.2409%;"><a href="{admin_url}">Visit URL</a></td>
                </tr>
                </tbody>
                </table>
                <p>{email_signature}</p>',
                'send_to' => '',
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2023-07-24 19:26:03',
                'updated_at' => '2023-07-24 19:57:44',
                'deleted_at' => null,
            ],
            23 => [
                'id' => 32,
                'name' => 'Website Order Success (To Customer)',
                'subject' => 'Your order placed successfully. Order# {order_number}',
                'message' => '<p><span style="color: #000000;"><span style="font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures;">Hello {owner_name},</span></span></p>
                <p><span style="color: #000000;"><span style="font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures;">Your order has been placed successfully. Your order details are mentioned below.</span></span></p>
                <p><span style="color: #000000;"><strong><span style="font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures;">Order # :&nbsp;</span></strong></span><span style="font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures;">{order_number}</span><br style="box-sizing: inherit; color: #d1d2d3; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: #222529;" /><span style="color: #000000; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures;"><strong>Order Date : </strong>{order_date}</span><br style="box-sizing: inherit; color: #d1d2d3; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: #222529;" /><span style="color: #000000;"><strong><span style="font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures;">Domain :&nbsp;</span></strong><span style="font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures;">{domain}</span></span><br style="box-sizing: inherit; color: #d1d2d3; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: #222529;" /><span style="color: #000000; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures;"><strong>Plan :</strong> {plan_title}</span><br style="box-sizing: inherit; color: #d1d2d3; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: #222529;" /><span style="color: #000000;"><span style="font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures;"><strong>Amount :</strong> {order_amount}</span></span></p>
                <p><span style="color: #000000;"><span style="font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures;">We will contact you soon for further process.</span></span></p>
                <p>&nbsp;</p>
                <p><span style="color: #000000;"><span style="font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures;"><span>{email_signature}</span><br /></span></span></p>',
                'send_to' => '',
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2024-03-22 14:59:43',
                'updated_at' => '2024-03-22 14:59:43',
                'deleted_at' => null,
            ],
            24 => [
                'id' => 33,
                'name' => 'Website Order Success (To Admins)',
                'subject' => 'New Website Order :  Order# {order_number}',
                'message' => '<p><span style="font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures;">Hello Admins,</span></p>
                <p><span style="font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures;">New order has been placed order details are mentioned below.</span></p>
                <p><strong><span style="font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures;">Order # :&nbsp;</span></strong><span style="font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures;">{order_number}</span><br style="box-sizing: inherit; color: #d1d2d3; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: #222529;" /><span style="color: #000000; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures;"><strong>Order Date :&nbsp;</strong>{order_date}</span><br style="box-sizing: inherit; color: #d1d2d3; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: #222529;" /><strong><span style="font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures;">Domain :&nbsp;</span></strong><span style="font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures;">{domain}</span><br style="box-sizing: inherit; color: #d1d2d3; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: #222529;" /><span style="color: #000000; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures;"><strong>Plan :</strong>&nbsp;{plan_title}</span><br style="box-sizing: inherit; color: #d1d2d3; font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures; background-color: #222529;" /><span style="font-family: Slack-Lato, Slack-Fractions, appleLogo, sans-serif; font-size: 15px; font-variant-ligatures: common-ligatures;"><strong>Amount :</strong>&nbsp;{order_amount}<br /><strong>Owner :&nbsp;&nbsp;</strong>{owner_name}<br /><strong>Order Link : <a href="{order_link}">View Order</a></strong></span></p>
                <p>{email_signature}</p>',
                'send_to' => '',
                'provider_id' => 3,
                'is_raw' => 0,
                'status' => 'Active',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2024-03-22 14:59:43',
                'updated_at' => '2024-03-22 14:59:43',
                'deleted_at' => null,
            ],
        ];

        $templates = collect($templates)
            ->map(function (array $template) use ($auditUserId): array {
                if (isset($template['status']) && is_string($template['status'])) {
                    $template['status'] = strtolower($template['status']);
                }

                $template['created_by'] = $auditUserId;
                $template['updated_by'] = $auditUserId;
                $template['deleted_by'] = null;

                return $template;
            })
            ->all();

        DB::table('email_templates')->insertOrIgnore($templates);

        // Reset PostgreSQL sequence to avoid duplicate key errors on next insert
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('email_templates', 'id'), COALESCE((SELECT MAX(id) FROM email_templates), 1))");
        }
    }
}
