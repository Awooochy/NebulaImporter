using UnityEngine;
using UnityEditor;
using System.IO;
using System.Collections.Generic;

public class AdvancedExporter : EditorWindow
{
    private string selectedFolderPath;
    private string temporaryExportPath;
    private List<AssetItem> assets = new List<AssetItem>();
    private Vector2 scrollPosition = Vector2.zero;

    [MenuItem("NEBULA IMPORTER/Extra Tools/\ud83d\udce6ADVANCED EXPORTER\ud83d\udce6")]
    static void Init()
    {
        AdvancedExporter window = (AdvancedExporter)EditorWindow.GetWindow(typeof(AdvancedExporter));
        window.Show();
    }

    void OnGUI()
    {
        Color backgroundColor = new Color(0.1f, 0.0f, 0.2f);
        EditorGUI.DrawRect(new Rect(0, 0, position.width, position.height), backgroundColor);
        
        GUI.contentColor = Color.yellow;

        GUILayout.Label("AdvancedExporter", EditorStyles.boldLabel);

        if (GUILayout.Button("Select Folder", GetDarkPurpleButtonStyle()))
        {
            selectedFolderPath = EditorUtility.OpenFolderPanel("Select Folder to Export", "", "");
            RefreshAssetList();
        }

        if (!string.IsNullOrEmpty(selectedFolderPath))
        {
            GUILayout.Label("Selected Folder: " + selectedFolderPath, EditorStyles.wordWrappedLabel);

            if (GUILayout.Button("Refresh", GetDarkPurpleButtonStyle()))
            {
                RefreshAssetList();
            }
            
            scrollPosition = EditorGUILayout.BeginScrollView(scrollPosition);

            EditorGUILayout.BeginVertical();
            DrawAssetList(assets, null);
            EditorGUILayout.EndVertical();

            EditorGUILayout.EndScrollView();

            EditorGUILayout.BeginHorizontal();
            if (GUILayout.Button("Check ALL", GetDarkPurpleButtonStyle()))
            {
                CheckAll(assets, true);
            }
            if (GUILayout.Button("Uncheck ALL", GetDarkPurpleButtonStyle()))
            {
                CheckAll(assets, false);
            }
            EditorGUILayout.EndHorizontal();

            if (GUILayout.Button("Export Package", GetDarkPurpleButtonStyle()))
            {
                ExportPackage();
            }
        }
        GUI.contentColor = Color.white;
    }
    
    private GUIStyle GetDarkPurpleButtonStyle()
    {
        GUIStyle style = new GUIStyle(GUI.skin.button);
        Color darkPurpleColor = new Color(0.2f, 0.0f, 0.4f);
        style.normal.textColor = Color.white;
        style.normal.background = MakeTex(1, 1, darkPurpleColor);
        style.hover.background = MakeTex(1, 1, darkPurpleColor * 1.2f);
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

    private void DrawAssetList(List<AssetItem> items, AssetItem parent)
    {
        EditorGUI.indentLevel++;

        foreach (var item in items)
        {
            EditorGUILayout.BeginHorizontal();

            bool newItemIsChecked = EditorGUILayout.ToggleLeft(item.Name, item.IsChecked);

            if (item.IsFolder)
            {
                item.IsExpanded = EditorGUILayout.Foldout(item.IsExpanded, GUIContent.none);
            }

            EditorGUILayout.EndHorizontal();

            if (item.IsExpanded)
            {
                DrawAssetList(item.Children, item);
            }

            if (newItemIsChecked != item.IsChecked)
            {
                item.IsChecked = newItemIsChecked;
                
                if (parent != null)
                {
                    parent.IsChecked = parent.Children.TrueForAll(child => child.IsChecked);
                }
                if (item.IsFolder)
                {
                    MarkChildren(item, item.IsChecked);
                }
            }
        }

        EditorGUI.indentLevel--;
    }

    private void MarkChildren(AssetItem parent, bool isChecked)
    {
        parent.IsChecked = isChecked;

        foreach (var child in parent.Children)
        {
            MarkChildren(child, isChecked);
        }
    }

    private void CheckAll(List<AssetItem> items, bool isChecked)
    {
        foreach (var item in items)
        {
            item.IsChecked = isChecked;

            if (item.IsFolder)
            {
                MarkChildren(item, isChecked);
            }
        }
    }

    private void RefreshAssetList()
    {
        assets.Clear();

        if (Directory.Exists(selectedFolderPath))
        {
            PopulateAssetList(selectedFolderPath, assets);
        }

        Repaint();
    }

    private void PopulateAssetList(string folderPath, List<AssetItem> items)
    {
        string[] directories = Directory.GetDirectories(folderPath);
        foreach (var directory in directories)
        {
            var folderItem = new AssetItem(Path.GetFileName(directory), true);
            items.Add(folderItem);
            PopulateAssetList(directory, folderItem.Children);
        }

        string[] files = Directory.GetFiles(folderPath);
        foreach (var file in files)
        {
            //Exclude meta files from appearing in the importer
            if (!file.EndsWith(".meta"))
            {
                items.Add(new AssetItem(Path.GetFileName(file), false));
            }
        }
    }

    private void ExportPackage()
    {
        List<string> selectedAssets = new List<string>();
        temporaryExportPath = Path.Combine(Application.dataPath, "TempExport");

        if (!Directory.Exists(temporaryExportPath))
        {
            Directory.CreateDirectory(temporaryExportPath);
        }

        CollectSelectedAssets(assets, selectedAssets);

        foreach (var assetPath in selectedAssets)
        {
            string destinationPath = Path.Combine(temporaryExportPath, Path.GetFileName(assetPath));
            File.Copy(assetPath, destinationPath, true);
        }

        if (selectedAssets.Count > 0)
        {
            //Refresh the AssetDatabase to make sure it recognizes the new files
            AssetDatabase.Refresh();

            string packagePath = EditorUtility.SaveFilePanel("Export Package", "", "CustomPackage", "unitypackage");

            if (!string.IsNullOrEmpty(packagePath))
            {
                AssetDatabase.ExportPackage("Assets/TempExport", packagePath, ExportPackageOptions.Recurse);
                Debug.Log("Package exported successfully: " + packagePath);
            }

            //Clean up temporary directory
            if (Directory.Exists(temporaryExportPath))
            {
                Directory.Delete(temporaryExportPath, true);
            }
        }
        else
        {
            Debug.LogWarning("No assets selected for export.");
        }
    }


    private void CollectSelectedAssets(List<AssetItem> items, List<string> selectedAssets)
    {
        foreach (var item in items)
        {
            if (item.IsChecked && !item.IsFolder)
            {
                selectedAssets.Add(Path.Combine(selectedFolderPath, item.Name));
            }

            if (item.IsExpanded)
            {
                CollectSelectedAssets(item.Children, selectedAssets);
            }
        }
    }

    private class AssetItem
    {
        public string Name { get; }
        public bool IsFolder { get; }
        public bool IsExpanded { get; set; }
        public bool IsChecked { get; set; }
        public List<AssetItem> Children { get; } = new List<AssetItem>();

        public AssetItem(string name, bool isFolder)
        {
            Name = name;
            IsFolder = isFolder;
            IsExpanded = false;
            IsChecked = false;
        }
    }
}
