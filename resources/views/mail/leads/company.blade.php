<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Results</title>
</head>

<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff; border-radius: 15px;">

        <!-- Logo Section -->
        <div style="text-align: center; padding-bottom: 20px;">
            <a href="https://app.thesvnetwork.com">
                <img src="{{ config('app.api_url') . '/storage/images/logo.png' }}" alt="SVNetwork Logo" style="max-width: 200px; height: auto;">
            </a>
        </div>

        <!-- Header Section -->
        <div style="text-align: left; padding-bottom: 20px;">
            <h2 style="color: #333333;">Hi {{ $data->company_name }}.</h2>
            <p style="color: #555555;">
                We’re reaching out with a unique opportunity! A customer in your area is looking for
                <strong>{{ $data['service']?->name }}</strong> services, and we believe your company might be the perfect fit.
            </p>
            <p style="color: #555555;">
                At this moment, we don’t have a registered company offering this service in this area in our database. However, we selected your business because we believe it could be a great fit for this client’s needs. The client reached out through <strong>SVNetwork</strong> seeking assistance in finding a company, which is why you are receiving this lead.
            </p>
            <p style="background-color: #8bfffd; padding: 10px; border-radius: 8px; font-weight: bold; color: #333333;">
                Take advantage of this opportunity to connect with a new client!
            </p>
        </div>

        <!-- Lead Information -->
        <div style="border: 1px solid #ccc; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <h3 style="color: #333333;">Project - {{ $data->title }}</h3>
            <p style="color: #555555;">{{ $data->description }}</p>
            <ul style="padding: 0; margin: 0; list-style: none; color: #555555;">
                @if (!empty($data->answers))
                    @foreach ($data->answers as $answer)
                        <li style="padding: 10px; background-color: #f9f9f9; margin-bottom: 10px; border-radius: 8px;">
                            <strong>{{ $answer->question->text }}</strong>: {{ $answer->text }}
                        </li>
                    @endforeach
                @endif
                @if (!empty($data->openAnswers))
                    @foreach ($data->openAnswers as $answer)
                        <li style="padding: 10px; background-color: #f9f9f9; margin-bottom: 10px; border-radius: 8px;">
                            <strong>{{ $answer->question_text }}</strong>: {{ $answer->text }}
                        </li>
                    @endforeach
                @endif
            </ul>
            @if (!empty($data->images))
                @foreach ($data->images as $image)
                    <a href="{{ $image->url }}" target="_blank" style="display: block; margin-bottom: 10px;">
                        <img src="{{ $image->url }}" alt="Project Image" style="max-width: 100%; height: auto; border-radius: 8px;">
                    </a>
                @endforeach
            @endif
        </div>

        <!-- Contact Information -->
        <div style="padding: 15px; border: 3px dotted #e45700; border-radius: 8px; margin-bottom: 20px;">
            <h3 style="color: #333333;">{{ $data['user']['name'] }} {{ $data['user']['surname'] }}</h3>
            <p style="color: #555555;">Phone: <a href="tel:+{{ $data['user']['phone'] }}" style="color: #fa5f1e;">{{ $data['user']['phone'] }}</a></p>
            <p style="color: #555555;">Email: <a href="mailto:{{ $data['user']['email'] }}" style="color: #fa5f1e;">{{ $data['user']['email'] }}</a></p>
        </div>

        <!-- CTA Section -->
        <div style="text-align: center; margin-bottom: 20px;">
            <a href="https://app.thesvnetwork.com/auth/register/pro" target="_blank" style="display: inline-block; background-color: #fa5f1e; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 8px;">
                Register to receive more leads like this
            </a>
        </div>

        <div>
           <table width="100%" cellspacing="0" cellpadding="0" border="0" style="font-family: Arial, sans-serif; color: #333;">
    <tr>
        <td align="center" style="padding: 20px;">
            <!-- Feature 1 -->
            <table cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; width: 100%; margin-bottom: 20px;">
                <tr>
                    <td align="center" style="padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                        <div style="margin-bottom: 10px;">
                            <div class="icon" style="width:64px;fill:rgb(189, 189, 189)"><svg aria-hidden="true" class="e-font-icon-svg e-fas-hands-helping"
                                viewBox="0 0 640 512" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M488 192H336v56c0 39.7-32.3 72-72 72s-72-32.3-72-72V126.4l-64.9 39C107.8 176.9 96 197.8 96 220.2v47.3l-80 46.2C.7 322.5-4.6 342.1 4.3 357.4l80 138.6c8.8 15.3 28.4 20.5 43.7 11.7L231.4 448H368c35.3 0 64-28.7 64-64h16c17.7 0 32-14.3 32-32v-64h8c13.3 0 24-10.7 24-24v-48c0-13.3-10.7-24-24-24zm147.7-37.4L555.7 16C546.9.7 527.3-4.5 512 4.3L408.6 64H306.4c-12 0-23.7 3.4-33.9 9.7L239 94.6c-9.4 5.8-15 16.1-15 27.1V248c0 22.1 17.9 40 40 40s40-17.9 40-40v-88h184c30.9 0 56 25.1 56 56v28.5l80-46.2c15.3-8.9 20.5-28.4 11.7-43.7z">
                                </path>
                            </svg></div>
                        </div>
                        <h3 style="font-size: 18px; color: #000; margin: 10px 0;">Less Competition</h3>
                        <p style="font-size: 14px; line-height: 1.5; color: #555;">We only show three results to clients, ensuring you stand out and have a better opportunity to win the job.</p>
                    </td>
                </tr>
            </table>

            <!-- Feature 2 -->
            <table cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; width: 100%; margin-bottom: 20px;">
                <tr>
                    <td align="center" style="padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                        <div style="margin-bottom: 10px;">
                            <div class="icon" style="width:64px;fill:rgb(189, 189, 189)"><svg aria-hidden="true" class="e-font-icon-svg e-fas-mouse"
                                viewBox="0 0 384 512" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M0 352a160 160 0 0 0 160 160h64a160 160 0 0 0 160-160V224H0zM176 0h-16A160 160 0 0 0 0 160v32h176zm48 0h-16v192h176v-32A160 160 0 0 0 224 0z">
                                </path>
                            </svg></div>
                        </div>
                        <h3 style="font-size: 18px; color: #000; margin: 10px 0;">Easy Management</h3>
                        <p style="font-size: 14px; line-height: 1.5; color: #555;">Simplify your workflow with our easy-to-use platform, giving you full control and transparency over prices and projects.</p>
                    </td>
                </tr>
            </table>

            <!-- Feature 3 -->
            <table cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; width: 100%; margin-bottom: 20px;">
                <tr>
                    <td align="center" style="padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                        <div style="margin-bottom: 10px;">
                            <div class="icon" style="width:64px;fill:rgb(189, 189, 189)"><svg aria-hidden="true" class="e-font-icon-svg e-far-money-bill-alt"
                                viewBox="0 0 640 512" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M320 144c-53.02 0-96 50.14-96 112 0 61.85 42.98 112 96 112 53 0 96-50.13 96-112 0-61.86-42.98-112-96-112zm40 168c0 4.42-3.58 8-8 8h-64c-4.42 0-8-3.58-8-8v-16c0-4.42 3.58-8 8-8h16v-55.44l-.47.31a7.992 7.992 0 0 1-11.09-2.22l-8.88-13.31a7.992 7.992 0 0 1 2.22-11.09l15.33-10.22a23.99 23.99 0 0 1 13.31-4.03H328c4.42 0 8 3.58 8 8v88h16c4.42 0 8 3.58 8 8v16zM608 64H32C14.33 64 0 78.33 0 96v320c0 17.67 14.33 32 32 32h576c17.67 0 32-14.33 32-32V96c0-17.67-14.33-32-32-32zm-16 272c-35.35 0-64 28.65-64 64H112c0-35.35-28.65-64-64-64V176c35.35 0 64-28.65 64-64h416c0 35.35 28.65 64 64 64v160z">
                                </path>
                            </svg></div>
                        </div>
                        <h3 style="font-size: 18px; color: #000; margin: 10px 0;">Transparent Pricing</h3>
                        <p style="font-size: 14px; line-height: 1.5; color: #555;">We offer complete price transparency, so you always know what you're paying for.</p>
                    </td>
                </tr>
            </table>

            <!-- CTA Buttons -->
            <table cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; width: 100%; text-align: center;">
                <tr>
                    <td style="padding: 10px;">
                        <a href="https://app.thesvnetwork.com/auth/register/pro" target="_blank"
                            style="display: inline-block; padding: 10px 20px; background-color: #fa5f1e; color: #fff; text-decoration: none; border-radius: 4px; font-size: 14px;">Register</a>
                    </td>
                    <td style="padding: 10px;">
                        <a href="https://thesvnetwork.com/contractors?utm_source=email-no-match" target="_blank"
                            style="display: inline-block; padding: 10px 20px; background-color: #6c757d; color: #fff; text-decoration: none; border-radius: 4px; font-size: 14px;">Learn More</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

        </div>
        <!-- Footer -->
        <div style="font-size: 12px; color: #777777; text-align: center; padding-top: 15px; border-top: 1px solid #ccc;">
            <p>
                This email was sent by SVNetwork. For more information, visit our <a href="{{ config('app.app_url') . '/legal/terms' }}" style="color: #fa5f1e;">Terms & Conditions</a>.
            </p>
        </div>

