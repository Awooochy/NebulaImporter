using UnityEditor;
using UnityEngine;
using UnityEngine.Networking;
using System.Collections;

public class NebulaCoreInfoWindow : EditorWindow
{
    private const string discordLink = "https://....../";
    private const string githubLink = "https://github.com/Awooochy";
    private const string announcementUrl = "https://....../getAnnouncements.php";

    private string currentAnnouncement = "Fetching announcements...";
    private Vector2 scrollPos; //Scrollable announcement text

    [MenuItem("NEBULA IMPORTER/\ud83d\udcacANNOUNCEMENTS\ud83d\udcac")]
    private static void ShowWindow()
    {
        NebulaCoreInfoWindow window = GetWindow<NebulaCoreInfoWindow>("Nebula Info");
        window.minSize = new Vector2(300, 200);
        window.FetchAnnouncements();
    }

    private void OnGUI()
    {
        Color backgroundColor = new Color(0.1f, 0.0f, 0.2f);
        EditorGUIUtility.DrawColorSwatch(new Rect(0, 0, position.width, position.height), backgroundColor);

        GUI.contentColor = Color.yellow;

        GUILayout.Label("Nebula Importer Information", EditorStyles.boldLabel);

        //Display announcement from the php
        GUILayout.Label("Latest Announcement:", EditorStyles.boldLabel);
        scrollPos = GUILayout.BeginScrollView(scrollPos, GUILayout.Height(100)); // Make the announcement scrollable
        GUILayout.Label(currentAnnouncement, EditorStyles.wordWrappedLabel);
        GUILayout.EndScrollView();

        GUILayout.Space(20);

        //Original info text
        GUILayout.Label("Welcome to Nebula Importer!\nThis is my personal importer, some files are reserved for private users, you can claim your private user account by having friends or trusted role in SHADER!GANG server.\nSimply DM Awooochy and I will make you an account.", EditorStyles.wordWrappedLabel);

        GUILayout.Space(20);

        GUILayout.BeginHorizontal();

        if (GUILayout.Button("Close", GetDarkPurpleButtonStyle()))
        {
            Close();
        }

        if (GUILayout.Button("Discord", GetDarkPurpleButtonStyle()))
        {
            Application.OpenURL(discordLink);
        }

        if (GUILayout.Button("GitHub", GetDarkPurpleButtonStyle()))
        {
            Application.OpenURL(githubLink);
        }

        GUILayout.EndHorizontal();

        GUI.contentColor = Color.white;
    }

    private void FetchAnnouncements()
    {
        EditorApplication.CallbackFunction updateDelegate = null;
        UnityWebRequest webRequest = UnityWebRequest.Get(announcementUrl);
        webRequest.SendWebRequest();

        updateDelegate = () =>
        {
            if (webRequest.isDone)
            {
                if (!webRequest.isNetworkError && !webRequest.isHttpError)
                {
                    //PHP returns JSON announcement
                    try
                    {
                        AnnouncementData data = JsonUtility.FromJson<AnnouncementData>(webRequest.downloadHandler.text);
                        currentAnnouncement = data.announcement;
                    }
                    catch (System.Exception e)
                    {
                        currentAnnouncement = "Error parsing announcement: " + e.Message;
                        Debug.LogError("Error parsing announcement JSON: " + e.Message);
                    }
                }
                else
                {
                    currentAnnouncement = "Failed to fetch announcements: " + webRequest.error;
                    Debug.LogError("Failed to fetch announcements: " + webRequest.error);
                }
                EditorApplication.update -= updateDelegate;
                Repaint();
            }
        };

        EditorApplication.update += updateDelegate;
    }

    //Deserialize the JSON from the PHP script
    [System.Serializable]
    private class AnnouncementData
    {
        public string announcement;
    }

    private GUIStyle GetDarkPurpleButtonStyle()
    {
        GUIStyle style = new GUIStyle(GUI.skin.button);
        Color darkPurpleColor = new Color(0.2f, 0.0f, 0.4f); //Darker purple
        style.normal.textColor = Color.white;
        style.normal.background = MakeTex(1, 1, darkPurpleColor);
        style.hover.background = MakeTex(1, 1, darkPurpleColor * 1.2f); //Darken on hover
        return style;
    }

    private Texture2D MakeTex(int width, int height, Color col)
    {
        Color[] pix = new Color[width * height];
        for (int i = 0; i < pix.Length; ++i)
        {
            pix[i] = col;
        }
        Texture2D result = new Texture2D(width, height);
        result.SetPixels(pix);
        result.Apply();
        return result;
    }
}