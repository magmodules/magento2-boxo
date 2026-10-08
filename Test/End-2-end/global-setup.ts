/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { type FullConfig } from '@playwright/test';

async function globalSetup(config: FullConfig) {
  process.env['NODE_TLS_REJECT_UNAUTHORIZED'] = '0';

  // The html reporter defaults to open: 'on-failure', and the VPS runner passes
  // --reporter='list,html,json' on the command line, which overrides the reporter options set in
  // playwright.config.ts. So a single failing test leaves Playwright serving the report on a port
  // and the process never exits — a failed run looks like a hung run, and costs half an hour to
  // find out otherwise. Set through the environment because that is the only channel the CLI
  // override does not win over.
  process.env['PLAYWRIGHT_HTML_OPEN'] = 'never';
  const { baseURL } = config.projects[0].use;

  await getAdminToken(baseURL);
}

const getAdminToken = async (baseURL: string) => {
  const username = process.env.MAGENTO_ADMIN_USER || 'exampleuser';
  const password = process.env.MAGENTO_ADMIN_PASS || 'examplepassword123';

  console.log('Requesting admin token from "' + baseURL + '"...');

  const response = await fetch(baseURL + 'rest/all/V1/integration/admin/token', {
    method: 'POST',
    headers: {
      'accept': 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ username, password }),
  });

  process.env.admin_token = await response.json();
  console.log('Admin token acquired.');
};

export default globalSetup;
