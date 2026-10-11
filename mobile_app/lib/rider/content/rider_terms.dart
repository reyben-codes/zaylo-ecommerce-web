/// The Rider Terms and Conditions shown during rider sign-up.
///
/// Keep [riderTermsVersion] equal to RiderApplication::TERMS_VERSION on the
/// server and bump both whenever this text changes, so each application
/// records which version the rider agreed to.
///
/// This is starter wording for the ZAYLO team to review with legal counsel
/// before launch; it is not legal advice.
library;

const riderTermsVersion = '2026-10-11';

const riderTermsTitle = 'ZAYLO Rider Terms and Conditions';

class RiderTermsSection {
  const RiderTermsSection(this.heading, this.body);

  final String heading;
  final String body;
}

const riderTermsSections = [
  RiderTermsSection(
    '1. About these terms',
    'These terms apply when you apply for and work as a delivery rider on the ZAYLO marketplace. '
        'By submitting your application you confirm that you have read and agree to them.',
  ),
  RiderTermsSection(
    '2. Eligibility',
    'You must be at least 18 years old, hold a valid driver\'s license for the vehicle you use, and have '
        'a vehicle with a current Official Receipt and Certificate of Registration (OR/CR).',
  ),
  RiderTermsSection(
    '3. Accurate information and documents',
    'Everything you submit, including your name, contact details, license, plate number, and OR/CR, must be '
        'true, current, and your own. ZAYLO may reject or suspend an account that uses false or someone else\'s '
        'information or documents.',
  ),
  RiderTermsSection(
    '4. Application review',
    'Applications are reviewed by the ZAYLO team, usually within 24 hours. You can sign in to the rider app only '
        'after your application is approved. ZAYLO may ask for more information or decline an application.',
  ),
  RiderTermsSection(
    '5. Keeping documents valid',
    'Keep your license and vehicle registration valid while you ride. Update them in your rider account before '
        'they expire; deliveries may be paused while a document is expired.',
  ),
  RiderTermsSection(
    '6. Safe and lawful riding',
    'Follow traffic laws, wear the required safety gear, and never ride under the influence of alcohol or drugs. '
        'You are responsible for your own conduct on the road.',
  ),
  RiderTermsSection(
    '7. Handling orders',
    'Handle every parcel with care, deliver it only to the intended recipient or address, and do not open, keep, '
        'or swap any item. Cash collected on delivery must be remitted in full as instructed.',
  ),
  RiderTermsSection(
    '8. Customer privacy',
    'Use buyers\' names, addresses, and phone numbers only to complete their deliveries. Do not copy, share, or '
        'contact customers for any other purpose.',
  ),
  RiderTermsSection(
    '9. Your personal data',
    'ZAYLO stores your application details and documents securely and uses them to verify your identity, review '
        'your application, and manage deliveries, in line with the Data Privacy Act of 2012.',
  ),
  RiderTermsSection(
    '10. Suspension and termination',
    'ZAYLO may suspend or end your rider access for breaking these terms, unsafe conduct, or repeated customer '
        'complaints. You may stop riding at any time by contacting ZAYLO support.',
  ),
  RiderTermsSection(
    '11. Changes to these terms',
    'ZAYLO may update these terms. When it does, you will be asked to read and accept the new version before '
        'taking further deliveries.',
  ),
];
