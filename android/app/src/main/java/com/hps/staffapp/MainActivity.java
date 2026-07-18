package com.hps.staffapp;

import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.os.Build;
import android.os.Bundle;

import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        createDefaultNotificationChannel();
    }

    /**
     * High-importance default channel (referenced from the manifest meta-data)
     * so FCM pushes heads-up with sound.
     */
    private void createDefaultNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                "hps_default",
                "HPS Staff notifications",
                NotificationManager.IMPORTANCE_HIGH
            );
            channel.setDescription("Events, gym session reminders and announcements");

            getSystemService(NotificationManager.class).createNotificationChannel(channel);
        }
    }
}
