/**
 * Elementor Lead Handler
 * 
 * שימוש:
 * 1. העתק קוד זה לתוך Elementor Form Widget > Advanced > Custom Code (JS)
 * 2. או הוסף <script src="..." ></script> בכל עמוד עם טופס
 * 
 * URL השרת: http://localhost:3001/api/elementor/leads
 */

(function() {
  'use strict';

  // הגדרה
  const CRM_ENDPOINT = 'http://localhost:3001/api/elementor/leads';
  const DEBUG = true; // שנה ל-false בייצור

  // Elementor hook - מופעל כשטופס נשלח
  if (typeof jQuery !== 'undefined') {
    jQuery(document).on('elementor_pro/forms/submit/response', function(event, response, handler) {
      if (DEBUG) console.log('📧 Elementor Form Submitted:', response);
      
      // קבל את נתוני הטופס
      const $form = jQuery(handler.widget.find('.elementor-form'));
      const formData = $form.serializeArray();
      const leadData = {};

      formData.forEach(field => {
        leadData[field.name] = field.value;
      });

      // שלח ל-CRM
      sendLeadToCRM(leadData);
    });
  }

  // אם Elementor אינו טוען, נשתמש ב-event listener סטנדרטי
  document.addEventListener('DOMContentLoaded', function() {
    // חפש טפסים של Elementor
    const forms = document.querySelectorAll('form.elementor-form, [data-form-id]');
    
    forms.forEach(form => {
      form.addEventListener('submit', function(e) {
        // אל תחסום את השליחה הרגילה, רק שדור גם למייל
        setTimeout(() => {
          const formData = new FormData(this);
          const leadData = Object.fromEntries(formData);
          sendLeadToCRM(leadData);
        }, 500);
      });
    });
  });

  /**
   * שלח ליד ל-CRM Server
   */
  async function sendLeadToCRM(leadData) {
    if (DEBUG) console.log('🚀 Sending lead to CRM:', leadData);

    try {
      const response = await fetch(CRM_ENDPOINT, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify(leadData),
        // CORS mode
        mode: 'cors',
        credentials: 'omit'
      });

      const result = await response.json();

      if (response.ok) {
        console.log('✅ Lead sent successfully:', result);
        triggerSuccessCallback(leadData, result);
      } else {
        console.warn('⚠️ Lead sent but server returned error:', result);
        triggerErrorCallback(leadData, result);
      }
    } catch (error) {
      console.error('❌ Error sending lead:', error);
      triggerErrorCallback(leadData, error.message);
    }
  }

  /**
   * Callback בהצלחה
   */
  function triggerSuccessCallback(leadData, serverResponse) {
    // התראה בדפדפן
    if ('Notification' in window && Notification.permission === 'granted') {
      new Notification('ליד חדש נקלט', {
        body: `התקבל ליד מ-${leadData.name || leadData.email || 'אתר'}`,
        icon: '📧'
      });
    }

    // Custom Event - אם רוצים לעשות משהו אחר בעמוד
    const event = new CustomEvent('leadSentToCRM', {
      detail: { lead: leadData, response: serverResponse }
    });
    document.dispatchEvent(event);

    // Log
    if (window.gtag) {
      gtag('event', 'lead_submitted', {
        event_category: 'engagement',
        event_label: leadData.email || 'unknown',
        value: 1
      });
    }
  }

  /**
   * Callback שגיאה
   */
  function triggerErrorCallback(leadData, error) {
    console.error('Lead submission error:', error);

    // עדיין שמור מקומית
    saveLeadLocally(leadData);

    // Custom Event
    const event = new CustomEvent('leadFailedToCRM', {
      detail: { lead: leadData, error: error }
    });
    document.dispatchEvent(event);
  }

  /**
   * שמור ליד באופן מקומי (localStorage) אם השרת לא פעיל
   */
  function saveLeadLocally(leadData) {
    try {
      const existingLeads = JSON.parse(localStorage.getItem('pendingLeads')) || [];
      existingLeads.push({
        timestamp: new Date().toISOString(),
        data: leadData
      });
      localStorage.setItem('pendingLeads', JSON.stringify(existingLeads));
      console.log('💾 Lead saved locally. Total pending:', existingLeads.length);
    } catch (e) {
      console.warn('Could not save lead locally:', e);
    }
  }

  /**
   * שדר לידים שנתקעו במקומי כשהשרת חוזר
   */
  async function resendPendingLeads() {
    try {
      const pendingLeads = JSON.parse(localStorage.getItem('pendingLeads')) || [];
      
      if (pendingLeads.length === 0) return;

      console.log('🔄 Resending', pendingLeads.length, 'pending leads...');

      for (const item of pendingLeads) {
        await sendLeadToCRM(item.data);
      }

      // נקה
      localStorage.removeItem('pendingLeads');
      console.log('✅ All pending leads resent');
    } catch (e) {
      console.error('Error resending pending leads:', e);
    }
  }

  // בדוק כל 30 שניות אם יש לידים ממתינים
  setInterval(resendPendingLeads, 30000);

  // Expose globally if needed
  window.CRMLeadHandler = {
    sendLead: sendLeadToCRM,
    resendPending: resendPendingLeads,
    getEndpoint: () => CRM_ENDPOINT
  };

  if (DEBUG) {
    console.log('✅ CRM Lead Handler loaded');
    console.log('📍 Endpoint:', CRM_ENDPOINT);
    console.log('🔧 Available at: window.CRMLeadHandler');
  }
})();

/**
 * דוגמה לשימוש ידני:
 * 
 * // שלח ליד ישירות
 * window.CRMLeadHandler.sendLead({
 *   name: 'דוד כהן',
 *   email: 'david@example.com',
 *   phone: '0501234567',
 *   message: 'ממש מעניין'
 * });
 * 
 * // בדוק לידים ממתינים
 * window.CRMLeadHandler.resendPending();
 */
