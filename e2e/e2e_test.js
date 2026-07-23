const puppeteer = require('puppeteer');
const http = require('http');

(async () => {
  const browser = await puppeteer.launch({ 
    headless: false, 
    args: ['--window-size=1280,800'] 
  });
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 800 });
  
  console.log("1. Navigating to POS application...");
  await page.goto('http://localhost:5173');

  // Handle Login if necessary
  try {
    await page.waitForSelector('input[type="email"]', { timeout: 3000 });
    console.log("   - Login required. Entering credentials...");
    await page.type('input[type="email"]', 'admin@pos.test');
    await page.type('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    // wait for network idle to ensure login finishes
    await new Promise(r => setTimeout(r, 2000));
  } catch (e) {
    console.log("   - No login form detected or already logged in.");
  }

  // Ensure we are on POS
  if (!page.url().includes('/pos')) {
    await page.goto('http://localhost:5173/pos');
    await new Promise(r => setTimeout(r, 2000));
  }

  console.log("2. Setting up network interception for session ID...");
  let pendingSessionId = null;
  let expectedAmount = 0.50; // default for test, wait for intercept

  // Listen to network responses to capture the session_id
  page.on('response', async (response) => {
    if (response.url().includes('/api/pending-payments') && response.request().method() === 'POST') {
      try {
        const json = await response.json();
        if (json && json.pending_payment && json.pending_payment.session_id) {
          pendingSessionId = json.pending_payment.session_id;
          expectedAmount = json.pending_payment.expected_amount;
          console.log(`   -> Captured Session ID: ${pendingSessionId} for Amount: $${expectedAmount}`);
        }
      } catch (e) {
         // ignore
      }
    }
  });

  console.log("3. Adding item to cart...");
  try {
    await new Promise(r => setTimeout(r, 2000));
    const clicked = await page.evaluate(() => {
        const product = document.querySelector('.product-card');
        if (product) {
            product.click();
            return true;
        }
        return false;
    });

    if (clicked) {
        console.log("   - Clicked a product card.");
    } else {
        console.log("   - Failed to find product card.");
    }
  } catch(e) {
    console.error("   - Failed to click product", e);
  }

  await new Promise(r => setTimeout(r, 1000));

  console.log("4. Initiating KHQR checkout...");
  try {
    // Click KHQR Payment Method Toggle
    const clickedKhqrToggle = await page.evaluate(() => {
        const buttons = Array.from(document.querySelectorAll('.payment-btn'));
        const btn = buttons.find(b => b.textContent.includes('KHQR'));
        if (btn) {
            btn.click();
            return true;
        }
        return false;
    });

    if (clickedKhqrToggle) {
        console.log("   - Selected KHQR payment method.");
    } else {
        console.log("   - Could not find KHQR payment toggle.");
    }

    await new Promise(r => setTimeout(r, 500));

    // Click Charge Button
    const clickedCharge = await page.evaluate(() => {
        const btn = document.querySelector('.charge-btn');
        if (btn && !btn.disabled) {
            btn.click();
            return true;
        }
        return false;
    });

    if (clickedCharge) {
        console.log("   - Clicked Charge button.");
    } else {
        console.log("   - Could not click Charge button (maybe disabled or not found).");
    }
  } catch(e) {
    console.error("   - Error clicking checkout", e);
  }

  // Wait for the modal and session ID to be captured
  await new Promise(r => setTimeout(r, 2000));

  console.log("5. Verifying KHQR Modal...");
  try {
    const isModalVisible = await page.evaluate(() => {
        return document.body.innerText.includes('Waiting for ABA');
    });
    if (isModalVisible) {
        console.log("   - Modal successfully appeared with 'Waiting for ABA'.");
    } else {
        console.log("   - Warning: 'Waiting for ABA' text not found in modal.");
    }
  } catch (e) {
    console.log("   - Warning: Error checking modal text.");
  }

  // Wait another second just in case
  await new Promise(r => setTimeout(r, 1000));

  if (!pendingSessionId) {
    console.error("   - Error: Could not capture pending session ID. Cannot trigger webhook.");
    await browser.close();
    return;
  }

  console.log(`6. Triggering mock live Telegram webhook for $${expectedAmount}...`);
  
  // Format string with correct amount
  const numericAmount = parseFloat(expectedAmount);
  const webhookText = `$${numericAmount.toFixed(2)} paid by VONG DAVID (*201) on Jul 22, 09:54 AM via ABA PAY at VONG DAVID. Trx. ID: 178468889718017`;
  
  const payload = JSON.stringify({
    message: {
      chat: { id: "-5369598416" },
      text: webhookText
    }
  });

  const options = {
    hostname: 'localhost',
    port: 8000,
    path: '/api/telegram/webhook',
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Content-Length': Buffer.byteLength(payload)
    }
  };

  const req = http.request(options, (res) => {
    console.log(`   - Webhook fired! Response Status: ${res.statusCode}`);
  });
  req.on('error', (e) => {
    console.error(`   - Problem with webhook request: ${e.message}`);
  });
  req.write(payload);
  req.end();

  console.log("7. Monitoring POS for Auto-Settle...");
  try {
    // Poll the page text for changes
    for (let i = 0; i < 20; i++) {
        const text = await page.evaluate(() => document.body.innerText);
        if (text.includes('Successful') || text.includes('Success')) {
            console.log("   - SUCCESS! Detected 'Payment Successful' message on UI.");
            break;
        }
        if (!text.includes('Waiting for ABA')) {
            console.log("   - SUCCESS! The modal closed automatically or moved forward.");
            break;
        }
        await new Promise(r => setTimeout(r, 1000));
        if (i === 19) console.log("   - Timeout waiting for Auto-Settle.");
    }
  } catch (e) {
    console.log("   - Error while monitoring.");
  }

  console.log("End-to-End test complete. Browser will remain open for 5 seconds.");
  await new Promise(r => setTimeout(r, 5000));
  await browser.close();
})();
