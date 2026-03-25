# 首次推送到 GitHub（vpn-saas-reseller）

若当前代码在 **monorepo** 内（路径如 **`2.0/php/B`**），要推送 **整个仓库** 时请参阅仓库根目录 **`GITHUB_PUSH.md`**。

---

1. 在 GitHub 网页：**New repository**  
   - Repository name：`vpn-saas-reseller`  
   - Description：`B 站分销商站`  
   - **不要**勾选 README / .gitignore / license

2. 在本机项目根目录执行：

```bash
git remote add origin git@github.com:YOUR_USER/vpn-saas-reseller.git
git branch -M main
git push -u origin main
```

3. 使用 GitHub CLI（可选）：

```bash
gh repo create vpn-saas-reseller --public --description "B 站分销商站"
git push -u origin main
```
