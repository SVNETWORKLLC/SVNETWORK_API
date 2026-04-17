<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Client Match - SVNetwork</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.5;
            color: #334155;
            background-color: #f8fafc;
        }
        .container {
            max-width: 520px;
            margin: 0 auto;
            background-color: #ffffff;
            overflow: hidden;
        }
        .header {
            background-color: #0f172a;
            color: #ffffff;
            padding: 48px 32px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            font-weight: 600;
            margin: 0;
            letter-spacing: -0.5px;
        }
        .content {
            padding: 40px 32px;
        }
        .greeting {
            font-size: 16px;
            margin-bottom: 16px;
            color: #0f172a;
            font-weight: 500;
        }
        .greeting strong {
            color: #0f172a;
            font-weight: 600;
        }
        .intro-text {
            font-size: 14px;
            line-height: 1.7;
            color: #475569;
            margin-bottom: 32px;
        }
        .section {
            margin-bottom: 32px;
        }
        .section-title {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .info-box {
            background-color: #f1f5f9;
            padding: 18px;
            border-radius: 6px;
            border: none;
        }
        .info-row {
            display: flex;
            margin-bottom: 12px;
            font-size: 13px;
        }
        .info-row:last-child {
            margin-bottom: 0;
        }
        .info-label {
            font-weight: 600;
            color: #0f172a;
            min-width: 110px;
            flex-shrink: 0;
        }
        .info-value {
            color: #475569;
            flex: 1;
            word-break: break-word;
            font-weight: 400;
        }
        .project-box {
            background-color: #f1f5f9;
            padding: 18px;
            border-radius: 6px;
            font-size: 13px;
            line-height: 1.7;
            color: #475569;
        }
        .cta-button {
            display: inline-block;
            background-color: #fa5f1e;
            color: #ffffff;
            padding: 14px 32px;
            text-decoration: none;
            font-weight: 600;
            margin: 32px 0;
            font-size: 14px;
            border: none;
            border-radius: 6px;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .cta-button:hover {
            background-color: #e55318;
        }
        .button-wrapper {
            text-align: center;
        }
        .cta-intro {
            background-color: #fef3f2;
            padding: 18px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.7;
            color: #475569;
            border-left: 4px solid #fa5f1e;
        }
           .images-grid {
               display: grid;
               grid-template-columns: repeat(2, 1fr);
               gap: 12px;
               margin-top: 16px;
           }
           .image-item {
               overflow: hidden;
               border-radius: 6px;
               background-color: #f1f5f9;
           }
           .image-link {
               display: block;
               width: 100%;
               height: 140px;
               overflow: hidden;
           }
           .image-link img {
               width: 100%;
               height: 100%;
               object-fit: cover;
               display: block;
               transition: transform 0.2s ease;
           }
           .image-link:hover img {
               transform: scale(1.05);
           }
        .footer {
            background-color: #f8fafc;
            padding: 28px 32px;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #94a3b8;
            text-align: center;
            line-height: 1.6;
        }
        .footer p {
            margin: 4px 0;
        }
        .link-text {
            word-break: break-all;
            color: #0f172a;
            font-size: 11px;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
            line-height: 1.6;
        }
        .link-text br {
            display: block;
            content: "";
            margin: 6px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>New Client Match</h1>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Greeting -->
            <div class="greeting">
                Hello <strong>{{ $notifiable->name }}</strong>
            </div>

            <p class="intro-text">
                You have received a new match on SVNetwork. A client is looking for the services you offer.
            </p>

            <!-- Client Information -->
            <div class="section">
                <div class="section-title">Client Information</div>
                <div class="info-box">
                    <div class="info-row">
                        <span class="info-label">Name</span>
                        <span class="info-value">{{ $project->user->name }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email</span>
                        <span class="info-value">{{ $project->user->email }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Phone</span>
                        <span class="info-value">{{ $project->user->phone ?? 'Not specified' }}</span>
                    </div>
                </div>
            </div>

            <!-- Project Description -->
            <div class="section">
                <div class="section-title">Project Description</div>
                <div class="project-box">
                    {{ $project->description }}
                </div>
            </div>

               <!-- Project Images -->
               @if ($project->images && $project->images->count() > 0)
               <div class="section">
                   <div class="section-title">Project Images</div>
                   <div class="images-grid">
                       @foreach ($project->images->take(4) as $image)
                       <div class="image-item">
                           <a href="{{ $image->url }}" target="_blank" class="image-link">
                               <img src="{{ $image->url }}" alt="Project image" />
                           </a>
                       </div>
                       @endforeach
                   </div>
               </div>
               @endif

            <!-- Call to Action -->
            @if ($link)
            <div class="cta-intro">
                <strong>Want more clients like this?</strong> SVNetwork is a platform that helps companies get clients. Claim your business profile to increase your visibility and attract more business opportunities.
            </div>

            <!-- Link -->
            <div class="button-wrapper">
                <a href="{{ $link }}" class="cta-button">Claim Business Profile</a>
            </div>
            <div class="link-text">
                If you cannot click the button, copy this URL:<br>
                {{ $link }}
            </div>
            @endif
            @if ($link2)
            <div>
                Go to your dashboard to claim this company and view the match details.
                 <div class="button-wrapper">
                <a href="{{ $link2 }}" class="cta-button">Go to Dashboard</a>
            </div>
            </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>SVNetwork © {{ date('Y') }}</p>
            <p>All rights reserved</p>
        </div>
    </div>
</body>
</html>
