package admin

import (
	"html/template"
	"io"
	"strings"
	"time"
)

type Report struct {
	ReportID           int
	ReportType         string
	UserReportCategory string
	DeckID             int
	DeckName           string
	IsPublic           bool
	ReportedUserID     int
	CreatorName        string
	UserID             int
	ReporterName       string
	Reason             string
	Status             string
}

type User struct {
	UserID         int
	Username       string
	Email          string
	Role           string
	PublicBanUntil string
	ReportBanUntil string
}

func (u User) IsPublicBanned() bool {
	if u.PublicBanUntil == "" {
		return false
	}
	t, err := time.Parse("2006-01-02 15:04:05", u.PublicBanUntil)
	if err != nil {
		return false
	}
	return t.After(time.Now())
}

func (u User) IsReportBanned() bool {
	if u.ReportBanUntil == "" {
		return false
	}
	t, err := time.Parse("2006-01-02 15:04:05", u.ReportBanUntil)
	if err != nil {
		return false
	}
	return t.After(time.Now())
}

type AdminViewData struct {
	Reports       []Report
	Users         []User
	SessionUserID int
}

func RenderAdmin(w io.Writer, data AdminViewData) error {
	funcMap := template.FuncMap{
		"nl2br": func(text string) template.HTML {
			escaped := template.HTMLEscapeString(text)
			return template.HTML(strings.ReplaceAll(escaped, "\n", "<br>"))
		},
		"slice": func(s string, start, end int) string {
			if len(s) < end {
				return s
			}
			return s[start:end]
		},
	}

	tmpl, err := template.New("admin.html").Funcs(funcMap).ParseFiles("app/Views/admin/admin.html")
	if err != nil {
		return err
	}

	return tmpl.Execute(w, data)
}